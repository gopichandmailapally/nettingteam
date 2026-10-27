
import json, re

with open("/var/www/gcm.enterprises/admin/data/documents.json", "r", encoding="utf-8") as f:
    docs = json.load(f)

print("Loaded docs in python:", len(docs))

# Recursively clean all string values in data structure
def clean_val(v):
    if isinstance(v, str):
        # Remove literal control characters (except standard printable or let json.dumps escape)
        # Specifically, clean up any literal ASCII 0-31 except standard whitespace
        # Or normalize newlines to \n
        v = v.replace("\r\n", "\n").replace("\r", "\n")
        # remove unprintable control chars
        v = re.sub(r"[\x00-\x08\x0b\x0c\x0e-\x1f]", "", v)
        return v
    elif isinstance(v, list):
        return [clean_val(x) for x in v]
    elif isinstance(v, dict):
        return {k: clean_val(val) for k, val in v.items()}
    return v

clean_docs = clean_val(docs)

# Dump to json file with standard ASCII escaping or ensure valid JSON for PHP
with open("/var/www/gcm.enterprises/admin/data/documents.json", "w", encoding="utf-8") as f:
    json.dump(clean_docs, f, indent=4, ensure_ascii=True)

print("Saved with ensure_ascii=True!")
