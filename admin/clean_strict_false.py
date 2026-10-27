
import json, re

with open("/var/www/gcm.enterprises/admin/data/documents.json", "r", encoding="utf-8") as f:
    text = f.read()

# Load with strict=False to allow raw newlines/control characters in strings
docs = json.loads(text, strict=False)
print(f"Successfully parsed {len(docs)} documents with strict=False!")

def clean_val(v):
    if isinstance(v, str):
        # Normalize newlines
        v = v.replace("\r\n", "\n").replace("\r", "\n")
        # Remove literal control characters (ASCII < 32 except \n and \t)
        v = re.sub(r"[\x00-\x08\x0b\x0c\x0e-\x1f]", "", v)
        return v
    elif isinstance(v, list):
        return [clean_val(x) for x in v]
    elif isinstance(v, dict):
        return {k: clean_val(val) for k, val in v.items()}
    return v

clean_docs = clean_val(docs)

# Save with ensure_ascii=False
with open("/var/www/gcm.enterprises/admin/data/documents.json", "w", encoding="utf-8") as f:
    json.dump(clean_docs, f, indent=4, ensure_ascii=False)

print("Saved clean documents.json!")
