
import json

with open("/var/www/gcm.enterprises/admin/data/documents.json", "r") as f:
    docs = json.load(f)

print("Python loaded docs successfully! Count:", len(docs))

# Let us test encoding each doc and decoding in PHP
import subprocess

for i, d in enumerate(docs):
    txt = json.dumps(d)
    res = subprocess.run(["php", "-r", "$d = json_decode(file_get_contents('php://stdin'), true); if ($d === null) echo json_last_error_msg();"], input=txt.encode("utf-8"), capture_output=True)
    err = res.stdout.decode().strip()
    if err:
        print(f"Doc {i} ({d.get('id')}) failed in PHP: {err}")
