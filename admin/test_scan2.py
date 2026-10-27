
import json, re

with open("/var/www/gcm.enterprises/admin/data/documents.json.bak_20260907_070408", "r", encoding="utf-8") as f:
    text = f.read()

matches = list(re.finditer(r'"id"\s*:\s*"((?:EST|BILL|INV)-\d{6}-\d{3})"', text))
base_docs = {}

for m in matches:
    doc_id = m.group(1)
    pos = m.start()
    open_b = text.rfind("{", 0, pos)
    if open_b == -1: continue
    
    depth = 0
    in_str = False
    escape = False
    for k in range(open_b, min(len(text), open_b + 30000)):
        ch = text[k]
        if escape:
            escape = False
            continue
        if ch == '\\':
            escape = True
            continue
        if ch == '"':
            in_str = not in_str
            continue
        if not in_str:
            if ch == '{': depth += 1
            elif ch == '}':
                depth -= 1
                if depth == 0:
                    candidate = text[open_b:k+1]
                    try:
                        # strict=True by default in python json.loads
                        d = json.loads(candidate)
                        if isinstance(d, dict) and d.get("id") == doc_id:
                            # Test if candidate json string can be dumped and loaded
                            test_s = json.dumps(d)
                            json.loads(test_s)
                            base_docs[doc_id] = d
                    except Exception as e:
                        print(f"Failed candidate for {doc_id}: {e}")
                    break

print(f"Strict valid base docs: {len(base_docs)}")
for doc_id, d in list(base_docs.items()):
    # Check if any doc has truncated/corrupted fields
    for k, v in d.items():
        if isinstance(v, str) and (""upd," in v or "\n" in v and len(v) > 2000):
            print(f"Suspicious field in {doc_id} -> {k}: {repr(v[:50])}")
