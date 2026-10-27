import re

path = '/var/www/gcm.enterprises/admin/assets/js/turnkey_court_viewer.js'
code = open(path).read()

# 1. Color replacements
code = code.replace('0xef4444', '0xff0000')
code = code.replace('#dc2626', '#ff0000')
code = code.replace('#ef4444', '#ff0000')
code = code.replace('#b91c1c', '#ff0000')

# 2. Update init3DCourt for 16:9
old_init = '''    container.innerHTML = \x27\x27;
    var width = container.clientWidth || 800;
    var height = container.clientHeight || 450;'''

new_init = '''    container.innerHTML = \x27\x27;
    var width = container.clientWidth || 311;
    var height = container.clientHeight || Math.round(width * 9 / 16);
    // Enforce 16:9 ratio
    if (Math.abs(width / height - 16 / 9) > 0.05) {
      height = Math.round(width * 9 / 16);
    }'''

assert old_init in code, 'old_init not found'
code = code.replace(old_init, new_init)

# 3. Update Camera in init3DCourt
old_cam = '''    var targetX = 0;
    var targetY = 0.15;
    var targetZ = 0.95;

    var elev = 20 * Math.PI / 180;
    var azim = 35 * Math.PI / 180;
    var R = 11.8;

    var camX = targetX + R * Math.cos(elev) * Math.cos(azim);
    var camY = targetY + R * Math.sin(elev);
    var camZ = targetZ + R * Math.cos(elev) * Math.sin(azim);

    var camera = new THREE.PerspectiveCamera(29.5, width / height, 0.1, 1000);'''

new_cam = '''    var targetX = 0.3;
    var targetY = 0.3;
    var targetZ = 0.0;

    var elev = 22 * Math.PI / 180;
    var azim = 38 * Math.PI / 180;
    var R = 13.5;

    var camX = targetX + R * Math.cos(elev) * Math.cos(azim);
    var camY = targetY + R * Math.sin(elev);
    var camZ = targetZ + R * Math.cos(elev) * Math.sin(azim);

    var camera = new THREE.PerspectiveCamera(30, 16 / 9, 0.1, 1000);'''

assert old_cam in code, 'old_cam not found'
code = code.replace(old_cam, new_cam)

open(path, 'w').write(code)
print('turnkey_court_viewer.js step 1 updated successfully!')
