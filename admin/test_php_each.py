
import json, subprocess

with open("/var/www/gcm.enterprises/admin/data/documents.json", "r", encoding="utf-8") as f:
    docs = json.load(f)

print("Python loaded", len(docs), "documents.")

# Test json_decode for each document in PHP
bad_docs = []
for i, d in enumerate(docs):
    doc_str = json.dumps(d)
    # test php decoding doc_str
    res = subprocess.run(["php", "-r", f"$d = json_decode(file_get_contents(php://stdin), true); if ($d === null) echo json_last_error_msg();"], input=doc_str.encode("utf-8"), capture_output=True)
    out = res.stdout.decode().strip()
    if out:
        print(f"Doc index {i} ID {d.get(id)} failed PHP decode: {out}")
        bad_docs.append((i, d.get("id")))

print("Total bad docs in PHP:", len(bad_docs))
