
import json

with open("/var/www/gcm.enterprises/admin/data/documents.json", "r", encoding="utf-8") as f:
    raw_text = f.read()

data = json.loads(raw_text)

def clean_val(v):
    if isinstance(v, str):
        res = []
        for ch in v:
            o = ord(ch)
            if o < 32:
                if ch in "\r\n":
                    res.append("\n")
                elif ch == "\t":
                    res.append(" ")
                else:
                    pass
            else:
                res.append(ch)
        return "".join(res)
    elif isinstance(v, list):
        return [clean_val(x) for x in v]
    elif isinstance(v, dict):
        return {clean_val(k): clean_val(val) for k, val in v.items()}
    return v

cleaned = clean_val(data)

with open("/var/www/gcm.enterprises/admin/data/documents.json", "w", encoding="utf-8") as f:
    json.dump(cleaned, f, indent=4, ensure_ascii=False)

print(f"Sanitized and saved {len(cleaned)} documents.")
