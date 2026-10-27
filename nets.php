<?php require_once __DIR__ . '/includes/whatsapp_helper.php'; ?>
<?php
/* NettingTeam – Dynamic SEO Pages: /{service}-in-{area} → nets.php?service=X&area=Y
   Data: 180 areas × 126 services = 22,680 pages (served by nets.php)
   Intent variants: 180 × 126 × 8 = 181,440 pages (served by n.php)
   Total dynamic: 204,120 pages */
require __DIR__.'/_data.php'; // $areas, $svc_data
if(false){$areas=[
'hyderabad'=>['label'=>'Hyderabad','zone'=>'city','near'=>['Gachibowli','Kondapur','Madhapur','HiTech City','Secunderabad','Kukatpally','Banjara Hills']],
'gachibowli'=>['label'=>'Gachibowli','zone'=>'west','near'=>['Madhapur','HiTech City','Kondapur','Financial District','Manikonda','Narsingi']],
'madhapur'=>['label'=>'Madhapur','zone'=>'west','near'=>['Gachibowli','HiTech City','Kondapur','Jubilee Hills','Banjara Hills']],
'kondapur'=>['label'=>'Kondapur','zone'=>'west','near'=>['Gachibowli','Madhapur','HiTech City','Miyapur','Kukatpally','Chandanagar']],
'hitech-city'=>['label'=>'HiTech City','zone'=>'west','near'=>['Madhapur','Gachibowli','Kondapur','Jubilee Hills','Kukatpally','KPHB']],
'banjara-hills'=>['label'=>'Banjara Hills','zone'=>'central','near'=>['Jubilee Hills','Madhapur','Panjagutta','Begumpet','Ameerpet']],
'jubilee-hills'=>['label'=>'Jubilee Hills','zone'=>'central','near'=>['Banjara Hills','Madhapur','HiTech City','Kondapur','Shaikpet']],
'kukatpally'=>['label'=>'Kukatpally','zone'=>'west','near'=>['KPHB','Miyapur','Kondapur','Bachupally','Hafeezpet','Nizampet']],
'secunderabad'=>['label'=>'Secunderabad','zone'=>'north','near'=>['Begumpet','Ameerpet','Malkajgiri','Alwal','Bowenpally','Marredpally']],
'ameerpet'=>['label'=>'Ameerpet','zone'=>'central','near'=>['Begumpet','Banjara Hills','Panjagutta','SR Nagar','Yousufguda']],
'begumpet'=>['label'=>'Begumpet','zone'=>'central','near'=>['Secunderabad','Ameerpet','Somajiguda','Panjagutta','Banjara Hills']],
'miyapur'=>['label'=>'Miyapur','zone'=>'west','near'=>['Kukatpally','Kondapur','Bachupally','KPHB','Chandanagar','Lingampally']],
'manikonda'=>['label'=>'Manikonda','zone'=>'south','near'=>['Gachibowli','Financial District','Puppalaguda','Attapur','Tolichowki']],
'financial-district'=>['label'=>'Financial District','zone'=>'west','near'=>['Gachibowli','Manikonda','Narsingi','Kondapur','Nanakramguda']],
'uppal'=>['label'=>'Uppal','zone'=>'east','near'=>['Nacharam','LB Nagar','Boduppal','Hayathnagar','Ramanthapur']],
'lb-nagar'=>['label'=>'LB Nagar','zone'=>'east','near'=>['Uppal','Dilsukhnagar','Hayathnagar','Saroornagar','Kothapet']],
'dilsukhnagar'=>['label'=>'Dilsukhnagar','zone'=>'east','near'=>['LB Nagar','Kothapet','Nacharam','Uppal','Malakpet']],
'kompally'=>['label'=>'Kompally','zone'=>'north','near'=>['Bachupally','Miyapur','Bowenpally','Alwal','Medchal']],
'bachupally'=>['label'=>'Bachupally','zone'=>'north','near'=>['Miyapur','Kompally','Kukatpally','Nizampet','Dundigal']],
'mehdipatnam'=>['label'=>'Mehdipatnam','zone'=>'south','near'=>['Tolichowki','Attapur','Manikonda','Banjara Hills','Shaikpet']],
'tolichowki'=>['label'=>'Tolichowki','zone'=>'south','near'=>['Mehdipatnam','Attapur','Manikonda','Gachibowli','Shaikpet']],
'attapur'=>['label'=>'Attapur','zone'=>'south','near'=>['Mehdipatnam','Tolichowki','Manikonda','Rajendra Nagar','Shaikpet']],
'nacharam'=>['label'=>'Nacharam','zone'=>'east','near'=>['Uppal','LB Nagar','Boduppal','Ramanthapur','Mallapur']],
'ecil'=>['label'=>'ECIL','zone'=>'north','near'=>['Malkajgiri','Sainikpuri','Nacharam','Alwal','Bowenpally']],
'malkajgiri'=>['label'=>'Malkajgiri','zone'=>'north','near'=>['ECIL','Secunderabad','Alwal','Bowenpally','Sainikpuri']],
'alwal'=>['label'=>'Alwal','zone'=>'north','near'=>['Kompally','Secunderabad','Bowenpally','Malkajgiri','Medchal']],
'bowenpally'=>['label'=>'Bowenpally','zone'=>'north','near'=>['Secunderabad','Alwal','Malkajgiri','Kompally','Marredpally']],
'boduppal'=>['label'=>'Boduppal','zone'=>'east','near'=>['Uppal','Nacharam','Ghatkesar','Hayathnagar','Mallapur']],
'hayathnagar'=>['label'=>'Hayathnagar','zone'=>'east','near'=>['LB Nagar','Uppal','Boduppal','Ghatkesar','Saroornagar']],
'vanasthalipuram'=>['label'=>'Vanasthalipuram','zone'=>'east','near'=>['LB Nagar','Saroornagar','Dilsukhnagar','Kothapet']],
'saroornagar'=>['label'=>'Saroornagar','zone'=>'east','near'=>['LB Nagar','Vanasthalipuram','Dilsukhnagar','Kothapet']],
'kothapet'=>['label'=>'Kothapet','zone'=>'east','near'=>['Dilsukhnagar','LB Nagar','Saroornagar','Malakpet','Amberpet']],
'malakpet'=>['label'=>'Malakpet','zone'=>'east','near'=>['Dilsukhnagar','Kothapet','Amberpet','Uppal','LB Nagar']],
'amberpet'=>['label'=>'Amberpet','zone'=>'central','near'=>['Malakpet','Kothapet','Ramanthapur','Nacharam','Himayatnagar']],
'ramanthapur'=>['label'=>'Ramanthapur','zone'=>'east','near'=>['Uppal','Nacharam','Amberpet','Mallapur','Boduppal']],
'trimulgherry'=>['label'=>'Trimulgherry','zone'=>'north','near'=>['Secunderabad','Malkajgiri','Bowenpally','Marredpally']],
'marredpally'=>['label'=>'Marredpally','zone'=>'north','near'=>['Secunderabad','Bowenpally','Trimulgherry','Begumpet']],
'panjagutta'=>['label'=>'Panjagutta','zone'=>'central','near'=>['Begumpet','Banjara Hills','Ameerpet','Somajiguda']],
'somajiguda'=>['label'=>'Somajiguda','zone'=>'central','near'=>['Begumpet','Panjagutta','Banjara Hills','Ameerpet']],
'yousufguda'=>['label'=>'Yousufguda','zone'=>'central','near'=>['Banjara Hills','Ameerpet','Panjagutta','Madhapur']],
'erragadda'=>['label'=>'Erragadda','zone'=>'central','near'=>['Ameerpet','SR Nagar','Kukatpally','Moosapet']],
'sr-nagar'=>['label'=>'SR Nagar','zone'=>'central','near'=>['Ameerpet','Erragadda','Kukatpally','Moosapet']],
'hafeezpet'=>['label'=>'Hafeezpet','zone'=>'west','near'=>['Kukatpally','Miyapur','Kondapur','KPHB','Nizampet']],
'nizampet'=>['label'=>'Nizampet','zone'=>'west','near'=>['Kukatpally','Bachupally','Miyapur','KPHB','Pragathi Nagar']],
'kphb'=>['label'=>'KPHB','zone'=>'west','near'=>['Kukatpally','Miyapur','HiTech City','Hafeezpet','Kondapur']],
'chandanagar'=>['label'=>'Chandanagar','zone'=>'west','near'=>['Miyapur','Kondapur','Lingampally','Serilingampally']],
'lingampally'=>['label'=>'Lingampally','zone'=>'west','near'=>['Miyapur','Chandanagar','Serilingampally','Kondapur']],
'serilingampally'=>['label'=>'Serilingampally','zone'=>'west','near'=>['Kondapur','Lingampally','Chandanagar','Miyapur']],
'nanakramguda'=>['label'=>'Nanakramguda','zone'=>'west','near'=>['Financial District','Gachibowli','Manikonda','Narsingi']],
'narsingi'=>['label'=>'Narsingi','zone'=>'south','near'=>['Manikonda','Financial District','Puppalaguda','Attapur']],
'puppalaguda'=>['label'=>'Puppalaguda','zone'=>'south','near'=>['Manikonda','Narsingi','Financial District','Attapur']],
'rajendra-nagar'=>['label'=>'Rajendra Nagar','zone'=>'south','near'=>['Attapur','Mehdipatnam','Tolichowki','Manikonda']],
'dundigal'=>['label'=>'Dundigal','zone'=>'north','near'=>['Kompally','Bachupally','Quthbullapur','Medchal','Alwal']],
'quthbullapur'=>['label'=>'Quthbullapur','zone'=>'north','near'=>['Kompally','Dundigal','Medchal','Alwal','Bachupally']],
'medchal'=>['label'=>'Medchal','zone'=>'north','near'=>['Kompally','Alwal','Dundigal','Quthbullapur','Bowenpally']],
'ghatkesar'=>['label'=>'Ghatkesar','zone'=>'east','near'=>['Boduppal','Uppal','Hayathnagar','Mallapur','Nacharam']],
'mallapur'=>['label'=>'Mallapur','zone'=>'east','near'=>['Nacharam','Uppal','Ramanthapur','Boduppal','Ghatkesar']],
'sainikpuri'=>['label'=>'Sainikpuri','zone'=>'north','near'=>['ECIL','Malkajgiri','Alwal','Bowenpally']],
'raidurg'=>['label'=>'Raidurg','zone'=>'south','near'=>['Gachibowli','Manikonda','Tolichowki','Shaikpet']],
'shaikpet'=>['label'=>'Shaikpet','zone'=>'south','near'=>['Jubilee Hills','Manikonda','Tolichowki','Mehdipatnam']],
'kavuri-hills'=>['label'=>'Kavuri Hills','zone'=>'west','near'=>['Jubilee Hills','Madhapur','HiTech City','Kondapur']],
'himayatnagar'=>['label'=>'Himayatnagar','zone'=>'central','near'=>['Nampally','Somajiguda','Banjara Hills','Amberpet']],
'nampally'=>['label'=>'Nampally','zone'=>'central','near'=>['Himayatnagar','Abids','Koti','Begumpet']],
'old-city'=>['label'=>'Old City','zone'=>'central','near'=>['Charminar','Nampally','Koti','Bahadurpura','Malakpet']],
'charminar'=>['label'=>'Charminar','zone'=>'central','near'=>['Old City','Nampally','Koti','Bahadurpura','Malakpet']],
'jeedimetla'=>['label'=>'Jeedimetla','zone'=>'north','near'=>['Kompally','ECIL','Quthbullapur','Moosapet']],
'moosapet'=>['label'=>'Moosapet','zone'=>'central','near'=>['Kukatpally','SR Nagar','Erragadda','Jeedimetla']],
'pragathi-nagar'=>['label'=>'Pragathi Nagar','zone'=>'west','near'=>['Kukatpally','Bachupally','Nizampet','KPHB']],
'balanagar'=>['label'=>'Balanagar','zone'=>'north','near'=>['Moosapet','Kukatpally','Jeedimetla','SR Nagar']],
'yapral'=>['label'=>'Yapral','zone'=>'north','near'=>['ECIL','Sainikpuri','Malkajgiri','Alwal']],
'karmanghat'=>['label'=>'Karmanghat','zone'=>'east','near'=>['Uppal','LB Nagar','Hayathnagar','Nacharam','Dilsukhnagar']],
'nagole'=>['label'=>'Nagole','zone'=>'east','near'=>['Uppal','LB Nagar','Kothapet','Malakpet','Dilsukhnagar']],
'shamshabad'=>['label'=>'Shamshabad','zone'=>'south','near'=>['Rajendra Nagar','Attapur','Karmanghat','Narsingi']],
];

$svc_data=[
'safety-nets'=>['display'=>'Balcony Safety Nets','parent'=>'service-balcony-safety-nets','price'=>'Rs. 12/sqft',
  'specs'=>[['Material','Russea™ HDPE UV Grade-A'],['Mesh','25–75mm'],['Thread','2–4mm'],['Load','200 kg'],['UV Life','10 Years'],['Warranty','5 Years Written']],
  'hero'=>'Russea™ HDPE balcony safety nets in %s — child-safe, UV-stabilized, 200kg load tested. Visit our office at Karmanghat. 5-year warranty.',
  'p1'=>'NettingTeam provides Russea™ certified HDPE balcony safety nets across all %s apartments, villas and high-rise buildings. Balcony falls are the leading child safety hazard in Hyderabad and a properly installed Russea™ net eliminates that risk permanently. Our nets are UV Grade-A for 10 years in Hyderabad climate, load-tested to 200 kg, and nearly transparent so they do not affect views from your %s home.',
  'p2'=>'We are the most trusted safety net installers in %s — 5000+ completed jobs across Hyderabad. Our certified team visits your %s home, takes measurements, installs with precision anchoring, and provides a 5-year written warranty. Walk-in welcome at Karmanghat office. Call 9912399334.',
  'faqs'=>[
    ['Best balcony safety net for apartments in %s?','Russea™ HDPE safety nets are the best for %s. UV Grade-A, 200 kg load, child and pet safe. From Rs. 12/sqft. Visit our office at Karmanghat — call 9912399334.'],
    ['Safety net cost in %s?','Safety nets in %s start from Rs. 12/sqft. Exact price depends on area and floor height. Call 9912399334 for a visit our office at Karmanghat and quote.'],
    ['Do safety nets block views in %s apartments?','No — Russea™ nets use 2mm transparent HDPE thread. Nearly invisible. No effect on views or airflow from your %s balcony.'],
    ['How long does installation take in %s?','2–4 hours for a standard balcony in %s. Same-day installation available after the visit our office at Karmanghat.'],
    ['Are safety nets safe for children in %s?','Yes — Russea™ HDPE nets have no sharp edges and mesh that prevents children and pets from squeezing through. 200 kg load tested.'],
    ['Warranty on safety nets in %s?','5-year written warranty on all safety net installations in %s — covering materials, UV performance, and workmanship.'],
  ],
],
'bird-nets'=>['display'=>'Bird Nets','parent'=>'service-bird-nets','price'=>'Rs. 12/sqft',
  'specs'=>[['Material','Russea™ HDPE UV Grade-A'],['Mesh','25–50mm'],['Bird Control','100% exclusion'],['Humane','Yes'],['UV Life','10 Years'],['Warranty','5 Years Written']],
  'hero'=>'Bird netting in %s — Russea™ HDPE anti-bird nets for balconies, rooftops and warehouses. Humane. 10-year UV life. Free visit.',
  'p1'=>'NettingTeam provides Russea™ certified HDPE anti-bird netting across all %s properties. Bird infestations cause droppings, disease risk, structural damage and constant noise. Physical HDPE exclusion netting is the only permanent solution. Our Russea™ bird nets (25–50mm mesh, UV Grade-A) are installed across residential balconies, commercial rooftops, warehouses, parking areas and agricultural plots in %s.',
  'p2'=>'Unlike temporary spikes, gels or sound devices, Russea™ HDPE bird netting provides permanent exclusion for 7–10 years. We have installed bird nets across 5000+ properties in Hyderabad, including hundreds of buildings in %s. Visit our office at Karmanghat, same-day or next-day installation, 5-year warranty. Call 9912399334.',
  'faqs'=>[
    ['Best bird net in %s?','Russea™ HDPE anti-bird netting — UV Grade-A, 25–50mm mesh, completely humane. 100 percent bird exclusion for all common Hyderabad birds in %s.'],
    ['Do bird nets exclude all birds in %s?','Yes — Russea™ 25mm mesh excludes sparrows, mynas, crows and pigeons. Remains nearly invisible on your %s property.'],
    ['Bird netting cost in %s?','Bird netting in %s starts from Rs. 12/sqft. Call 9912399334 for a visit our office at Karmanghat and exact quote.'],
    ['Is bird netting humane in %s?','Yes — completely humane. Birds cannot enter the netted area and find alternative spots naturally. No traps or chemicals.'],
    ['Bird net installation time in %s?','2–4 hours for most jobs in %s. All materials brought on-site. Same-day installation after site visit.'],
    ['Do you serve areas near %s?','Yes — we serve %s and all surrounding localities. Call 9912399334 for same-day availability.'],
  ],
],
'pigeon-nets'=>['display'=>'Pigeon Nets','parent'=>'service-pigeon-nets','price'=>'Rs. 12/sqft',
  'specs'=>[['Material','Russea™ HDPE UV Grade-A'],['Mesh','50–75mm'],['Pigeon Control','100% exclusion'],['Method','Physical exclusion'],['UV Life','7–10 Years'],['Warranty','5 Years Written']],
  'hero'=>'Pigeon nets in %s — permanent pigeon control with Russea™ HDPE nets. 100 percent exclusion. 5000+ pigeon-free homes. Visit our office at Karmanghat.',
  'p1'=>'NettingTeam provides the most effective pigeon control solution in %s: Russea™ certified HDPE pigeon exclusion netting. Pigeons nesting on balconies, AC units, water tanks and window ledges cause droppings, health hazards, noise and property damage. Physical netting is the ONLY permanent solution — Russea™ HDPE pigeon nets (50–75mm mesh, UV Grade-A) deliver exactly that in %s.',
  'p2'=>'We have made 5000+ Hyderabad properties permanently pigeon-free, including hundreds in %s. Visit our office at Karmanghats, same-day measurement, next-day installation. 5-year written warranty. No pigeons guaranteed. Call 9912399334 for your visit our office at Karmanghat in %s today.',
  'faqs'=>[
    ['Best pigeon control solution in %s?','Physical HDPE exclusion netting is the only permanent solution. Russea™ pigeon nets (50–75mm mesh) give 100 percent pigeon exclusion for all %s apartments and buildings.'],
    ['Do pigeon repellents work in %s?','No — repellents, gels, spikes and sounds are all temporary. Pigeons habituate within days. Physical netting is the only permanent solution.'],
    ['Pigeon net cost in %s?','Pigeon nets in %s start from Rs. 12/sqft. Call 9912399334 for a visit our office at Karmanghat and exact quote.'],
    ['How do you install pigeon nets in a high-rise in %s?','Our trained team uses professional anchoring systems for high-rise buildings in %s, up to 20+ floors with full safety protocol.'],
    ['How long do pigeon nets last in %s?','Russea™ pigeon nets are UV Grade-A rated for 7–10 years. 5-year written warranty from installation date.'],
    ['Can pigeons damage the nets in %s?','No — Russea™ HDPE breaking strength far exceeds any force pigeons can apply. Nets remain intact for 7–10 years with no maintenance.'],
  ],
],
'sports-nets'=>['display'=>'Sports Nets','parent'=>'service-sports-nets','price'=>'Rs. 25/sqft',
  'specs'=>[['Material','Russea™ Nylon/PP Twisted'],['Twine','2–6mm'],['Breaking Load','120 kgf'],['Sports','Cricket, Football, Badminton'],['UV Life','5–7 Years'],['Warranty','3 Years Written']],
  'hero'=>'Sports nets in %s — cricket nets, football nets, badminton courts. Russea™ certified materials. Turnkey installation. Free consultation.',
  'p1'=>'NettingTeam provides professional sports netting installation across %s for cricket practice nets, box cricket courts, football goal nets, badminton nets, volleyball nets and basketball courts. We use Russea™ certified nylon and PP sports nets — used by professional sports academies across India. Breaking load up to 120 kgf, 5–7 year UV life, suitable for all outdoor and indoor sports in %s.',
  'p2'=>'Whether you are setting up a box cricket facility, a school cricket practice area or a residential sports court in %s, our team handles everything from frame fabrication to net installation and artificial turf. Call 9912399334 for a free consultation and quote for your sports project in %s.',
  'faqs'=>[
    ['What sports nets do you install in %s?','We install cricket practice nets, football goal nets, badminton nets, volleyball nets, basketball nets and box cricket cage nets in %s. Russea™ nylon/PP, FIFA and BWF standard.'],
    ['Cricket net cost in %s?','Cricket practice net setup in %s starts from Rs. 45,000 for a single lane. Box cricket full cage from Rs. 1.5 lakh. Call 9912399334.'],
    ['Do you set up box cricket courts in %s?','Yes — full box cricket cage in %s with Russea™ nets, GI frame, optional turf and boundary. Turnkey setup in 3–5 days.'],
    ['Sports net warranty in %s?','3-year written warranty on all sports net installations in %s. Russea™ materials rated 5–7 years.'],
    ['Do you install sports nets at schools in %s?','Yes — we install at schools and sports academies across %s. Bulk project pricing available.'],
    ['How long for sports net installation in %s?','Simple cricket net frame: 1–2 days. Full box cricket cage in %s: 3–5 days.'],
  ],
],
'cricket-nets'=>['display'=>'Cricket Nets','parent'=>'service-sports-nets','price'=>'From Rs. 45,000',
  'specs'=>[['Material','Russea™ Knotted Nylon'],['Twine','3–5mm'],['Mesh','25–40mm'],['Frame','GI/MS Tubular'],['Turf','Optional artificial grass'],['Warranty','3 Years Written']],
  'hero'=>'Cricket nets in %s — practice net installation, box cricket setup, Russea™ knotted nylon nets. Turnkey. Free consultation.',
  'p1'=>'NettingTeam is the leading cricket net installer in %s, providing complete cricket practice net setups, box cricket court construction and net maintenance. We use Russea™ certified knotted nylon nets (3–5mm twine, 25–40mm mesh) which withstand hard ball impact for 5–7 years. Single practice lane for your society in %s, multi-lane academy setup or commercial box cricket facility — we handle it all end-to-end.',
  'p2'=>'Our %s cricket net installations include GI tubular frame construction, Russea™ net installation, boundary nets, optional artificial turf and proper anchor systems. Dozens of installations across Hyderabad including %s and surrounding areas. Call 9912399334 for a visit our office at Karmanghat and detailed project quote.',
  'faqs'=>[
    ['Cricket practice net cost in %s?','Single lane (10x60ft) in %s starts from Rs. 45,000. Box cricket full cage from Rs. 1.5 lakh. Call 9912399334 for exact quote.'],
    ['Box cricket court setup in %s?','Yes — full box cricket setup in %s: cage, Russea™ knotted nets, GI frame, optional turf and boundary. Ready in 3–5 days.'],
    ['What net is used for cricket practice?','Russea™ knotted nylon (3–5mm twine, 25–40mm mesh). Withstands hard ball, lasts 5–7 years. Same standard used by academies across India.'],
    ['Terrace cricket net installation in %s?','Yes — we specialise in terrace cricket net installations in %s using lightweight GI frames with safe anchoring.'],
    ['Do you provide artificial turf in %s?','Yes — artificial grass cricket turf from Rs. 45/sqft as part of our box cricket package in %s.'],
    ['Can the net area be multi-sport in %s?','Yes — our cage designs in %s can be configured for cricket, badminton and football with appropriate nets for each.'],
  ],
],
'box-cricket-nets'=>['display'=>'Box Cricket Setup','parent'=>'service-sports-nets','price'=>'From Rs. 1.5 Lakh',
  'specs'=>[['Cage Size','20x40ft to 30x60ft+'],['Net Material','Russea™ Knotted Nylon 3–5mm'],['Frame','MS/GI Heavy Duty'],['Turf','Optional artificial grass'],['Height','12–20ft standard'],['Warranty','3 Years Written']],
  'hero'=>'Box cricket setup in %s — complete turnkey box cricket court with Russea™ nets, GI frame, optional turf. From Rs. 1.5L. Visit our office at Karmanghat.',
  'p1'=>'NettingTeam is Hyderabad top box cricket court installer with dozens of complete setups across %s and surrounding areas. Our full-service box cricket setup covers: heavy-duty MS/GI tubular cage, Russea™ knotted nylon nets (3–5mm) for all sides and top, optional artificial grass cricket turf, boundary nets and access doors. We handle small residential society courts and large commercial box cricket facilities in %s.',
  'p2'=>'Starting from Rs. 1.5 lakh for a standard 20x40ft court, our box cricket setups in %s are built to last 5–7 years with minimal maintenance. Terrace, open ground and indoor setups all available. Call 9912399334 for a visit our office at Karmanghat and project estimate in %s. Your box cricket facility will be ready to play within 5 working days.',
  'faqs'=>[
    ['Box cricket setup cost in %s?','Starts from Rs. 1.5 lakh for a 20x40ft court in %s including frame and nets. Larger commercial setups quoted on site survey. Call 9912399334.'],
    ['What is included in box cricket setup in %s?','GI/MS cage frame, Russea™ knotted nylon side and top nets, optional artificial turf, boundary markings and access door. Turnkey ready-to-play.'],
    ['How long for box cricket installation in %s?','3–5 working days from site confirmation to ready-to-play in %s.'],
    ['Rooftop box cricket setup in %s?','Yes — we regularly install rooftop box cricket in %s apartment complexes with lightweight frames and proper load distribution.'],
    ['Minimum space for box cricket in %s?','Minimum 20x40ft cleared area. We handle everything from there in %s.'],
    ['Do you supply turf for box cricket in %s?','Yes — artificial grass cricket turf in %s from Rs. 45/sqft. Pitch-coloured turf and crease marking also available.'],
  ],
],
'invisible-grills'=>['display'=>'Invisible Grills','parent'=>'service-invisible-grills','price'=>'Rs. 200/sqft',
  'specs'=>[['Material','SS316 Marine Grade'],['Cable Dia','4–6mm'],['Spacing','50–100mm'],['Track','Aluminium Alloy'],['Corrosion','Marine grade, rust-proof'],['Warranty','5 Years Written']],
  'hero'=>'Invisible grills in %s — SS316 marine-grade cable grills for balconies. Rust-proof, child-safe, unobstructed views. 5-year warranty.',
  'p1'=>'NettingTeam installs SS316 marine-grade stainless steel invisible grills across all %s apartments, penthouses and villas. Invisible grills use tensioned 4–6mm SS316 cables between precision aluminium tracks to create a near-invisible, extremely strong safety barrier. Unlike SS304 or MS grills, SS316 never rusts — even in Hyderabad monsoon humidity. Child-safe at 50mm spacing, full panoramic views maintained.',
  'p2'=>'Our %s invisible grill installations are completed by trained specialists with CNC-precision drilling, correct cable tensioning and professional finishing. 5-year warranty on all work. Hundreds of premium apartments fitted in %s and surrounding areas. Call 9912399334 for a visit our office at Karmanghat and quote.',
  'faqs'=>[
    ['What are invisible grills in %s?','SS316 cable invisible grills use stainless steel cables tensioned between aluminium tracks to create a near-invisible child-safe safety barrier for balconies in %s.'],
    ['Do invisible grills rust in %s monsoon?','No — SS316 marine-grade steel is completely rust and corrosion resistant, even in Hyderabad monsoon humidity. Never rusts.'],
    ['Invisible grill cost in %s?','Invisible grills in %s start from Rs. 200/sqft. Call 9912399334 for a visit our office at Karmanghat and exact quote.'],
    ['Are invisible grills child-safe in %s?','Yes — at 50mm cable spacing, children cannot squeeze through or get limbs stuck. Safest and most aesthetic balcony safety option for %s families.'],
    ['How are invisible grills installed in %s?','Precise anchor points drilled into RCC/brick, aluminium tracks installed, 4–6mm SS316 cables tensioned to spec. Clean one-day installation.'],
    ['Warranty on invisible grills in %s?','5-year written warranty on SS316 cables, aluminium tracks and workmanship for all %s installations.'],
  ],
],
'cloth-hangers'=>['display'=>'Ceiling Cloth Hangers','parent'=>'service-cloth-hangers','price'=>'From Rs. 2,500',
  'specs'=>[['Type','Ceiling-mounted pulley system'],['Material','Aluminium/GI'],['Load','25–30 kg'],['Operation','Manual pulley or motorized'],['Rods','2–6 rods standard'],['Warranty','2 Years Written']],
  'hero'=>'Ceiling cloth hangers in %s — space-saving overhead drying systems for apartments. Manual and motorized options. From Rs. 2,500.',
  'p1'=>'NettingTeam installs ceiling-mounted cloth drying hangers across all %s apartments and homes. As apartment sizes shrink across Hyderabad, ceiling cloth hangers solve the problem of where to dry clothes — they utilise unused overhead ceiling space and keep floor and balcony areas clear. We offer manual pulley systems (4–6 rods) and motorized electric drying racks for premium apartments in %s.',
  'p2'=>'Our %s cloth hanger installations use corrosion-resistant aluminium and GI hardware, rated to 25–30 kg. Installation takes 1–2 hours with no mess. 2-year written warranty. Call 9912399334 to book a visit our office at Karmanghat in %s today.',
  'faqs'=>[
    ['What cloth hanger types in %s?','Ceiling pulley systems, wall-mounted folding racks, balcony overhead hangers and motorized ceiling drying racks — all installed in %s.'],
    ['Ceiling cloth hanger cost in %s?','From Rs. 2,500 for a basic 4-rod manual system in %s. Motorized from Rs. 8,000. Visit our office at Karmanghat — call 9912399334.'],
    ['Suitable for small apartments in %s?','Yes — ceiling cloth hangers are perfect for small apartments in %s. They use overhead space, freeing floor area completely.'],
    ['Load capacity of ceiling hangers in %s?','Our systems in %s are load-rated to 25–30 kg per installation — easily handles multiple sets of heavy wet clothes.'],
    ['Installation time in %s?','1–2 hours in %s. Our team brings all hardware including ceiling anchors, pulley system and rods.'],
    ['Work in low-ceiling apartments in %s?','Yes — designed for 9–10ft ceilings in %s. Rods lower to comfortable hanging height and raise to ceiling level when drying.'],
  ],
],
'bird-control'=>['display'=>'Bird Control Services','parent'=>'service-bird-nets','price'=>'Rs. 12/sqft',
  'specs'=>[['Method','Physical exclusion netting'],['Material','Russea™ HDPE UV Grade-A'],['Coverage','Balconies, rooftops, warehouses'],['Humane','Yes'],['UV Life','10 Years'],['Warranty','5 Years Written']],
  'hero'=>'Bird control services in %s — permanent bird exclusion with Russea™ HDPE netting. All bird species. 5000+ properties done. Free visit.',
  'p1'=>'NettingTeam provides professional bird control services in %s using the most effective permanent method: Russea™ HDPE physical exclusion netting. Bird problems in %s — pigeon droppings, sparrow nests in AC units, crow damage, myna infestations — are all permanently solved with the right netting. Unlike temporary repellents that lose effectiveness within weeks, Russea™ HDPE nets provide complete bird exclusion for 7–10 years.',
  'p2'=>'Our bird control team surveys your %s property, identifies all bird entry and roosting points, and installs customised Russea™ netting for each area — balconies, rooftops, AC ledges, water tanks, windows and building facades. 5-year warranty. Over 5000 bird-free properties delivered across Hyderabad including hundreds in %s. Call 9912399334 for a visit our Karmanghat office today.',
  'faqs'=>[
    ['Bird control methods in %s?','Russea™ HDPE physical exclusion netting — the most effective, permanent, humane bird control in %s. 100 percent exclusion permanently.'],
    ['Does bird control work for pigeons in %s?','Yes — we control all species including pigeons, crows, mynas and sparrows in %s using Russea™ nets with appropriate mesh sizes.'],
    ['Bird control cost in %s?','Bird control services in %s start from Rs. 12/sqft. Commercial buildings quoted after visit our Karmanghat office. Call 9912399334.'],
    ['How long does bird control last in %s?','Russea™ HDPE bird control netting lasts 7–10 years in Hyderabad. 5-year written warranty from installation date.'],
    ['Is bird control safe for children in %s?','Yes — exclusion netting is completely safe for children, pets and all non-target animals in %s.'],
    ['Bird control for commercial buildings in %s?','Yes — we provide bird control for commercial buildings, warehouses, hotels and institutions in %s.'],
  ],
],
];} // end if(false) dead-code block — live data comes from _data.php

// ---- PARSE & VALIDATE --------------------------------------
$sv = trim(preg_replace('/[^a-z0-9-]/','',strtolower($_GET['service']??'')));
$ar = trim(preg_replace('/[^a-z0-9-]/','',strtolower($_GET['area']??'')));

// ---- FUZZY SLUG FALLBACK (strips hyphens and tries to find a match) -------
if(!isset($svc_data[$sv])){
  $sv_plain = str_replace('-','',$sv);
  foreach(array_keys($svc_data) as $k){ if(str_replace('-','',$k)===$sv_plain){ $sv=$k; break; } }
}
if(!isset($areas[$ar])){
  $ar_plain = str_replace('-','',$ar);
  foreach(array_keys($areas) as $k){ if(str_replace('-','',$k)===$ar_plain){ $ar=$k; break; } }
}
// Redirect to canonical URL if slug was corrected
if(isset($svc_data[$sv])&&isset($areas[$ar])){
  $canon = '/'.$sv.'-in-'.$ar;
  $req   = strtok($_SERVER['REQUEST_URI'] ?? '','?');
  if($req !== $canon){ header('HTTP/1.1 301 Moved Permanently'); header('Location: '.$canon); exit; }
}
if(!isset($svc_data[$sv])||!isset($areas[$ar])){
  header('HTTP/1.0 404 Not Found');
  include 'includes/header.php';
  echo '<section class="hero" style="padding:6rem 0 3rem"><div class="container"><h1 class="hero-title">Page Not Found</h1><p>The page you requested does not exist.</p><a href="/" class="btn btn-primary" style="margin-top:1rem">Go Home</a></div></section>';
  include 'includes/footer.php';
  exit;
}

$s   = $svc_data[$sv];
$a   = $areas[$ar];
$A   = $a['label'];

// ---- Resolve city/state name from zone ----------------------
$_zone_city_map = [
  // Hyderabad localities
  'city'=>'Hyderabad','west'=>'Hyderabad','east'=>'Hyderabad',
  'north'=>'Hyderabad','south'=>'Hyderabad','central'=>'Hyderabad','hyderabad'=>'Hyderabad',
  // Telangana cities
  'telangana'=>'Telangana','warangal'=>'Warangal','hanamkonda'=>'Hanamkonda',
  'karimnagar'=>'Karimnagar','nizamabad'=>'Nizamabad','khammam'=>'Khammam',
  'mahabubnagar'=>'Mahabubnagar','nalgonda'=>'Nalgonda','suryapet'=>'Suryapet',
  // Andhra Pradesh
  'andhra-pradesh'=>'Andhra Pradesh',
  'visakhapatnam'=>'Visakhapatnam','vijayawada'=>'Vijayawada','guntur'=>'Guntur',
  'tirupati'=>'Tirupati','nellore'=>'Nellore','kurnool'=>'Kurnool','kadapa'=>'Kadapa',
  'anantapur'=>'Anantapur','rajahmundry'=>'Rajahmundry','kakinada'=>'Kakinada',
  // Karnataka
  'karnataka'=>'Karnataka','bengaluru'=>'Bengaluru','mysuru'=>'Mysuru',
  'mangaluru'=>'Mangaluru','hubli'=>'Hubli','belagavi'=>'Belagavi',
  'kalaburagi'=>'Kalaburagi','davangere'=>'Davangere','shivamogga'=>'Shivamogga',
  // Tamil Nadu
  'tamil-nadu'=>'Tamil Nadu','chennai'=>'Chennai','coimbatore'=>'Coimbatore',
  'madurai'=>'Madurai','trichy'=>'Trichy','salem'=>'Salem','tiruppur'=>'Tiruppur',
  'erode'=>'Erode','tirunelveli'=>'Tirunelveli','vellore'=>'Vellore',
  'thanjavur'=>'Thanjavur','puducherry'=>'Puducherry',
  // Kerala
  'kerala'=>'Kerala','kochi'=>'Kochi','thiruvananthapuram'=>'Thiruvananthapuram',
  'kozhikode'=>'Kozhikode','thrissur'=>'Thrissur','kollam'=>'Kollam',
  'kannur'=>'Kannur','palakkad'=>'Palakkad','malappuram'=>'Malappuram',
  // Goa
  'goa'=>'Goa',
];
$city_name  = $_zone_city_map[$a['zone']] ?? $A; // fallback to area label
$is_hyd     = in_array($a['zone'], ['city','west','east','north','south','central','hyderabad']);
// For Hyderabad areas: "Gachibowli, Hyderabad" | Others: "Marathahalli, Bengaluru"
$A_city     = ($city_name === $A) ? $A : $A.', '.$city_name;
// State label for schema
$_state_map = [
  'Hyderabad'=>'Telangana','Warangal'=>'Telangana','Hanamkonda'=>'Telangana',
  'Karimnagar'=>'Telangana','Nizamabad'=>'Telangana','Khammam'=>'Telangana',
  'Mahabubnagar'=>'Telangana','Nalgonda'=>'Telangana','Suryapet'=>'Telangana',
  'Telangana'=>'Telangana',
  'Visakhapatnam'=>'Andhra Pradesh','Vijayawada'=>'Andhra Pradesh',
  'Guntur'=>'Andhra Pradesh','Tirupati'=>'Andhra Pradesh',
  'Nellore'=>'Andhra Pradesh','Kurnool'=>'Andhra Pradesh','Kadapa'=>'Andhra Pradesh',
  'Anantapur'=>'Andhra Pradesh','Rajahmundry'=>'Andhra Pradesh','Kakinada'=>'Andhra Pradesh',
  'Andhra Pradesh'=>'Andhra Pradesh',
  'Bengaluru'=>'Karnataka','Mysuru'=>'Karnataka','Mangaluru'=>'Karnataka',
  'Hubli'=>'Karnataka','Belagavi'=>'Karnataka','Kalaburagi'=>'Karnataka',
  'Karnataka'=>'Karnataka',
  'Chennai'=>'Tamil Nadu','Coimbatore'=>'Tamil Nadu','Madurai'=>'Tamil Nadu',
  'Trichy'=>'Tamil Nadu','Salem'=>'Tamil Nadu','Tiruppur'=>'Tamil Nadu',
  'Erode'=>'Tamil Nadu','Tirunelveli'=>'Tamil Nadu','Vellore'=>'Tamil Nadu',
  'Thanjavur'=>'Tamil Nadu','Puducherry'=>'Puducherry','Tamil Nadu'=>'Tamil Nadu',
  'Kochi'=>'Kerala','Thiruvananthapuram'=>'Kerala','Kozhikode'=>'Kerala',
  'Thrissur'=>'Kerala','Kollam'=>'Kerala','Kannur'=>'Kerala',
  'Palakkad'=>'Kerala','Malappuram'=>'Kerala','Kerala'=>'Kerala',
  'Goa'=>'Goa',
];
require_once __DIR__ . '/includes/geo_resolver.php';

// Resolve full state and location dynamically from Master Geo Resolver
$geoResolved = resolveSmartGeoLocation($ar);
$geoParts = array_map('trim', explode(',', $geoResolved));
$resolvedState = end($geoParts);
if (!empty($resolvedState) && $resolvedState !== 'India') {
    $state_name = $resolvedState;
} else {
    $state_name = $_state_map[$city_name] ?? 'Telangana';
}

if ($is_hyd) {
    $brandLoc = ($city_name === $A) ? 'Hyderabad, Telangana' : $A . ', Hyderabad, Telangana';
} else {
    $brandLoc = ($city_name === $A) ? ($A . ', ' . $state_name) : ($A . ', ' . $state_name);
}

// ---- META --------------------------------------------------
$page_title       = $s['display'].' in '.$A_city.' | #1 Dealer, Supplier & Installation – NettingTeam '.$brandLoc;
$hero_raw         = sprintf($s['hero'], $A);
if (!$is_hyd) {
    $hero_raw     = str_replace([', Hyderabad', 'in Hyderabad', 'across Hyderabad'], [', '.$state_name, 'in '.$state_name, 'across '.$state_name], $hero_raw);
}
$page_description = strtolower($s['display']).' in '.$A.' — Authorized Russea™ dealer, wholesale supplier & certified installation experts. NettingTeam: '.$A_city.'\'s most trusted safety net company. '.$hero_raw.' Call 9912399334 for free site assessment.';
$page_keywords    = strtolower($s['display']).' '.$A.', '.strtolower($s['display']).' dealer '.$A.', '.strtolower($s['display']).' supplier '.$city_name.', '.strtolower($s['display']).' wholesaler '.$city_name.', '.strtolower($s['display']).' manufacturer '.$city_name.', '.strtolower($s['display']).' installation experts '.$A.', '.strtolower($s['display']).' retailer '.$A.', '.strtolower($s['display']).' price '.$A.', gcm enterprises '.$A.', russea™ nets dealer '.$city_name;
$page_canonical   = 'https://nettingteam.com/'.$sv.'-in-'.$ar;
$breadcrumbs      = [['name'=>'Services','url'=>'https://nettingteam.com/services'],['name'=>$s['display'],'url'=>'https://nettingteam.com/'.$s['parent']],['name'=>$s['display'].' in '.$A]];

// ---- FAQ SCHEMA --------------------------------------------
$faq_items = [];
foreach($s['faqs'] as $f){
  $faq_items[] = '{"@type":"Question","name":"'.addslashes(sprintf($f[0],$A,$A,$A)).'","acceptedAnswer":{"@type":"Answer","text":"'.addslashes(sprintf($f[1],$A,$A,$A)).'"}}';
}
$page_schema  = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"FAQPage","mainEntity":['.implode(',',$faq_items).']}</script>';

// ---- SERVICE SCHEMA (rich result for each service+area page) ----
$page_schema .= '<script type="application/ld+json">'.json_encode([
  '@context'    => 'https://schema.org',
  '@type'       => 'Service',
  'name'        => $s['display'].' in '.$A,
  'alternateName'=> $s['display'].' '.$A.' '.$city_name,
  'description' => sprintf($s['hero'],$A),
  'serviceType' => $s['display'],
  'provider'    => [
    '@type'     => 'LocalBusiness',
    '@id'       => 'https://nettingteam.com/#organization',
    'name'      => 'NettingTeam',
    'telephone' => '+919912399334',
    'address'   => ['@type'=>'PostalAddress','streetAddress'=>'Plot No. 68, MRR Colony, Karmanghat','addressLocality'=>'Hyderabad','addressRegion'=>'Telangana','postalCode'=>'500079','addressCountry'=>'IN'],
  ],
  'areaServed'  => ['@type'=>'Neighborhood','name'=>$A,'containedInPlace'=>['@type'=>'City','name'=>$city_name]],
  'url'         => $page_canonical,
  'offers'      => ['@type'=>'Offer','price'=>preg_replace('/[^0-9.]/','',$s['price']),'priceCurrency'=>'INR','seller'=>['@type'=>'Organization','name'=>'NettingTeam']],
  'aggregateRating' => ['@type'=>'AggregateRating','ratingValue'=>'4.9','ratingCount'=>'312','bestRating'=>'5','worstRating'=>'1'],
  'brand'       => ['@type'=>'Brand','name'=>'Russea™'],
]).'</script>';

// ---- DEALER/SUPPLIER TRUST BLURB ---------------------------
$dealer_blurb = 'NettingTeam is the authorized Russea™ brand dealer, wholesale supplier and certified installation expert for '.htmlspecialchars($s['display']).' in '.htmlspecialchars($A_city).'. As a direct factory-authorized retailer and bulk distributor, we source all materials straight from Russea™ — ensuring genuine products at wholesale prices with zero middlemen. You can walk in to inspect stock, purchase retail or in bulk, or call for wholesale dealer pricing. Our certified installation team serves '.$A.' and all surrounding areas with walk-in at our Karmanghat, Hyderabad offices.';

// ---- HELPERS -----------------------------------------------
$specs_html='';
foreach($s['specs'] as $i=>$r){
  $bg=$i%2==0?'background:#f9fafb;':'';
  $specs_html.='<tr style="'.$bg.'"><td style="padding:0.5rem;font-weight:600;border-bottom:1px solid #e5e7eb">'.htmlspecialchars($r[0]).'</td><td style="padding:0.5rem;border-bottom:1px solid #e5e7eb">'.htmlspecialchars($r[1]).'</td></tr>';
}
$nearby_html='';
foreach($a['near'] as $n){
  // Find slug for this nearby area label
  $nslug='';
  foreach($areas as $k=>$v){ if($v['label']===$n){ $nslug=$k; break; } }
  if($nslug){
    $nearby_html.='<a href="/'.$sv.'-in-'.htmlspecialchars($nslug).'" style="background:white;border:1px solid #e5e7eb;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.82rem;font-weight:500;display:flex;align-items:center;gap:0.4rem;text-decoration:none;color:inherit;transition:background .15s" onmouseover="this.style.background=\'#eff6ff\'" onmouseout="this.style.background=\'white\'"><i class="fas fa-map-marker-alt" style="color:var(--color-primary);font-size:0.7rem"></i>'.htmlspecialchars($n).'</a>';
  } else {
    $nearby_html.='<div style="background:white;border:1px solid #e5e7eb;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.82rem;font-weight:500;display:flex;align-items:center;gap:0.4rem"><i class="fas fa-map-marker-alt" style="color:var(--color-primary);font-size:0.7rem"></i>'.htmlspecialchars($n).'</div>';
  }
}
$faq_html='';
foreach($s['faqs'] as $f){
  $faq_html.='<div class="card"><h4 style="color:var(--color-primary);margin-bottom:0.75rem"><i class="fas fa-question-circle"></i> '.htmlspecialchars(sprintf($f[0],$A,$A,$A)).'</h4><p>'.htmlspecialchars(sprintf($f[1],$A,$A,$A)).'</p></div>';
}

include 'includes/header.php';
?>
<section class="hero" style="padding:6rem 0 3rem"><div class="container"><div class="hero-content">
<span style="display:inline-block;background:var(--color-primary);color:white;padding:0.35rem 1rem;border-radius:50px;font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:1rem">Russea™ Certified &bull; <?=htmlspecialchars($A)?></span>
<h1 class="hero-title"><?=htmlspecialchars($s['display'])?> in <?=htmlspecialchars($A_city)?></h1>
<p class="hero-subtitle"><?=htmlspecialchars(sprintf($s['hero'],$A))?></p>
<div class="hero-buttons">
<a href="tel:+919912399334" class="btn btn-primary btn-lg"><i class="fas fa-phone"></i> Call: 9912399334</a>
<a href="contact.php" class="btn btn-accent btn-lg"><i class="fas fa-comment-dots"></i> Get Free Quote</a>
</div></div></div></section>

<section class="section"><div class="container"><div class="grid grid-2" style="gap:3rem;align-items:start">
<div><h2><?=htmlspecialchars($s['display'])?> in <?=htmlspecialchars($A)?> &mdash; Expert Local Service</h2>
<p style="font-size:1.05rem;line-height:1.8;margin-top:1rem"><?=htmlspecialchars(sprintf($s['p1'],$A,$A,$A))?></p>
<p style="font-size:1.05rem;line-height:1.8;margin-top:0.75rem"><?=htmlspecialchars(sprintf($s['p2'],$A,$A,$A))?></p>
<p style="font-size:1rem;line-height:1.8;margin-top:0.75rem;padding:0.9rem 1.1rem;background:#fffbeb;border-left:4px solid var(--color-primary);border-radius:6px"><?=$dealer_blurb?></p>
<div class="card" style="background:#fff5f5;border-color:var(--color-primary);margin-top:1.5rem">
<h4 style="margin-bottom:1rem"><i class="fas fa-certificate" style="color:var(--color-primary)"></i> Why <?=htmlspecialchars($A)?> Customers Choose NettingTeam</h4>
<ul class="service-features" style="margin:0">
<li><strong>Authorized Russea™ Dealer &amp; Wholesale Supplier</strong></li>
<li><strong>Direct from Manufacturer — No Middlemen, Factory Prices</strong></li>
<li><strong>Certified Installation Experts — 10+ Years Experience</strong></li>
<li><strong>Retail &amp; Wholesale — Buy 1 Unit or in Bulk</strong></li>
<li><strong>5000+ completed installations across Hyderabad</strong></li>
<li><strong>Walk-in welcome at Karmanghat office, Hyderabad</strong></li>
<li><strong>Russea™ ISO 9001:2015 certified materials</strong></li>
<li><strong>5-Year Written Warranty on every job</strong></li>
</ul></div></div>
<div><div class="card" style="border-color:var(--color-primary)">
<h3 style="margin-bottom:1rem">Product Specifications</h3>
<table style="width:100%;font-size:0.9rem;border-collapse:collapse"><?=$specs_html?></table>
<div style="margin-top:1rem;padding:0.75rem;background:#f0fdf4;border-radius:8px;text-align:center"><strong>Visit Our Office at Karmanghat &amp; Custom Quote</strong> &mdash; Call 9912399334</div>
<a href="tel:+919912399334" class="btn btn-primary" style="width:100%;display:block;margin-top:0.75rem"><i class="fas fa-phone"></i> Call: 9912399334</a>
<a href="<?= getSmartWhatsAppUrl() ?>" target="_blank" class="btn" style="width:100%;display:block;margin-top:0.5rem;background:#25d366;color:white;border:none;padding:0.7rem 1rem;border-radius:8px;font-weight:600;text-align:center;text-decoration:none"><i class="fab fa-whatsapp"></i> WhatsApp for <?=htmlspecialchars($A)?> Quote</a>
</div></div></div></div></section>

<?php
// ── CONTENT VARIATION LAYER ────────────────────────────────────────────────
// Deterministic variant 0-4: same URL always renders same content
$v   = abs(crc32($sv.$ar)) % 5;
$zmap= ['west'=>'western Hyderabad','east'=>'east Hyderabad','north'=>'north Hyderabad','south'=>'south Hyderabad','central'=>'central Hyderabad','city'=>'all of Hyderabad city'];
$zd  = $zmap[$a['zone']] ?? $city_name;
$dn  = htmlspecialchars($s['display']);
$An      = htmlspecialchars($A);
$An_city = htmlspecialchars($A_city);
$n3  = htmlspecialchars(implode(', ', array_slice($a['near'],0,3)));
$n5  = htmlspecialchars(implode(', ', array_slice($a['near'],0,5)));

// ── 5 VARIANTS PER INTENT ─────────────────────────────────────────────────
$dealer_body = [
"<p>Property owners in <strong>{$An}</strong> searching for authorized {$dn} dealers get factory-direct pricing at NettingTeam — the only Russea™ certified dealer serving {$zd}. We stock genuine materials, issue Russea™ authenticity certificates, and offer walk-in retail as well as bulk wholesale. Our Karmanghat dealer store is accessible from {$An} within minutes and serves surrounding areas including {$n3}.</p><p>Purchasing from an unauthorized local dealer in {$An} is a common mistake — counterfeit HDPE nets lack UV stabilizers and fail within 1–2 years. NettingTeam stocks only Russea™ ISO 9001:2015 certified materials with documented breaking strength and UV Grade-A ratings. Every purchase includes a Russea™ warranty card and material certificate. Walk in today — inspect before you buy.</p>",
"<p>As the authorized Russea™ {$dn} dealer for {$An} and {$zd}, NettingTeam provides genuine manufacturer-certified materials with zero adulteration. {$An} customers can walk in, inspect stock, verify Russea™ certificates and purchase at dealer-direct prices — no inflated retail markup. We supply both individual homeowners and large contractors across {$An}, {$n3} and beyond.</p><p>Our dealer authorization covers the complete Russea™ product range — HDPE nets, nylon nets, anchors, SS cables and installation hardware — all sourced direct from factory. Buying dealer-direct in {$An} means you get the same materials used in premium housing projects at prices that beat any local shop.</p>",
"<p>NettingTeam holds official Russea™ dealer status for {$dn} across {$zd}, with full coverage of {$An}. Unlike unauthorized shops that resell unbranded or imitation nets, our dealer inventory is 100% traceable to Russea™ factory batches. Every roll of netting sold in {$An} carries a Russea™ batch code that customers can verify directly with the manufacturer.</p><p>Our {$An} dealer team is available 7 days a week — walk in at Karmanghat or call 9912399334 for a same-day response. Retail quantities (single balcony) and wholesale bulk (builder projects) are both handled from the same dealer stock at transparent published pricing. No hidden charges, no weekend surcharges for {$An} customers.</p>",
"<p>The right {$dn} dealer in {$An} makes a lifetime of difference — substandard materials installed today become expensive problems within 2 years. NettingTeam as the authorized Russea™ dealer in {$zd} ensures every {$An} customer receives UV Grade-A rated materials with documented 7–10 year outdoor life. Our dealer warranty is backed by Russea™ manufacturer — not just our word.</p><p>Dealers operating without Russea™ authorization in {$An} cannot offer genuine manufacturer warranty, material certificates or batch traceability. NettingTeam displays all Russea™ authorization documents at our Karmanghat showroom. {$An} customers are welcome to inspect credentials before purchase. Call 9912399334 for dealer pricing on any quantity.</p>",
"<p>The only dealer in {$An} with direct Russea™ factory authorization for {$dn} is NettingTeam — eliminating the distributor chain that typically adds 25–35% to material costs. {$An} homeowners, housing societies and contractors get factory prices with zero compromise on quality. Our Karmanghat dealer store stocks all Russea™ grades and mesh sizes for same-day purchase and immediate installation.</p><p>NettingTeam dealer services in {$An} include: material supply only, supply-with-installation, or installation-only using customer-supplied Russea™ materials. All three options come with our 5-year service warranty. We serve {$An} and nearby {$n3} with equal priority — response time under 2 hours for urgent installation requests. Call 9912399334.</p>",
];

$price_body = [
"<p>The price of {$dn} in <strong>{$An}</strong> depends on your exact covered area in sqft, floor height, net specification and site complexity. NettingTeam provides a 100% transparent written quote — no verbal estimates that change later, no hidden charges. Our {$An} pricing is all-inclusive: Russea™ certified materials, professional installation, corrosion-resistant anchors, hardware and a 5-year written warranty. One price, one invoice.</p><p>Price benchmarks for {$An}: standard residential balcony netting starts from Rs. {$s['price']} and scales with area. High-rise floors (above 10th) may have a small safety-equipment surcharge. Non-rectangular areas or custom shapes add a minor cutting charge. Commercial properties in {$An} are quoted after site survey. Call 9912399334 for a free on-site measurement and detailed written estimate.</p>",
"<p>Getting an accurate {$dn} price in <strong>{$An}</strong> requires a site visit — because no two properties are identical. NettingTeam offers free site measurement in {$An}: our certified technician visits, measures every dimension, assesses floor height and structural constraints, and provides a detailed written quote. No surprises — the quoted price is exactly what you pay. Russea™ factory-direct sourcing keeps our {$An} prices 15–20% below competitors using distributor-sourced materials.</p><p>What drives {$An} pricing: (1) Area in sqft — larger area = lower per-sqft rate; (2) Floor level — ground to 10th floor same rate; (3) Net grade — standard HDPE vs premium UV Grade-A; (4) Frame or track work if required. NettingTeam provides itemized quotes so {$An} customers can see exactly what they are paying for. Call 9912399334 or walk in at Karmanghat.</p>",
"<p>In <strong>{$An}</strong>, NettingTeam maintains the most competitive {$dn} pricing in {$zd} — because we source directly from Russea™ manufacturer with zero distributor markup. Our {$An} price includes everything needed for a complete installation: supply, installation labor, drilling, anchors, hardware and warranty. No add-ons after the quote. No post-installation billing surprises for {$An} customers.</p><p>Price factors specific to {$An}: building age affects anchor complexity (older buildings may need heavier anchors), floor height above 10th floor requires safety harness equipment for our team, and monsoon-window scheduling may affect appointment timing. All these factors are accounted for in the upfront written quote — nothing charged separately after installation in {$An}. Call 9912399334 for your estimate.</p>",
"<p>Property owners across <strong>{$An}</strong> and {$zd} ask NettingTeam for {$dn} prices regularly — our policy is always a written quote after free site measurement. Why? Because verbal price estimates lead to disputes. Our written quotes are valid for 30 days and are price-locked — material costs are not passed on to {$An} customers between quote and installation. Russea™ materials sourced factory-direct keep our pricing consistently competitive across {$An} regardless of market fluctuations.</p><p>The cheapest {$dn} price in {$An} is not always the best value — low-price competitors often use non-UV-rated HDPE that yellows and breaks within 2 years. NettingTeam prices include Russea™ UV Grade-A materials with 7–10 year outdoor life. Calculated over the product lifespan, our {$An} price is the lowest cost-per-year of any supplier in {$zd}. Call 9912399334.</p>",
"<p>The best way to get the right {$dn} price in <strong>{$An}</strong> is a free site visit from NettingTeam — our team visits {$An} properties the same day you call, takes precise measurements and provides a written all-inclusive quote within 24 hours. No obligation, no pressure sales. Russea™ factory-direct pricing means our {$An} quotes are consistently lower than local competitors. We serve {$An} and nearby {$n3} from our Karmanghat base — call 9912399334 today.</p><p>Our {$An} price guarantee: the written quote is the final price. If our installation team discovers any additional complexity on-site (rare), they inform you before proceeding — never bill extra without your approval. This transparent pricing policy has earned NettingTeam 5-star reviews from hundreds of {$An} customers. Installation price starts from Rs. {$s['price']} — call for your exact estimate.</p>",
];

$install_body = [
"<p>NettingTeam has completed over 5,000 {$dn} installations across Hyderabad, including hundreds of properties in <strong>{$An}</strong> and nearby {$n3}. Our certified installation team follows a precise 5-step protocol: site survey, measurement, anchor planning, precision installation and post-installation quality check. Every installation in {$An} is done by our own trained team — we never subcontract to untrained workers.</p><p>High-rise installation in {$An} buildings is handled with full safety harness equipment and documented safety protocols. Our team has experience across all building types in {$zd} — new construction, old concrete, brick, RCC and prefab structures. Standard residential installations in {$An} are typically completed in 2–4 hours with minimal disruption and no wall damage beyond precision anchor points. 5-year written warranty from day one.</p>",
"<p>Professional {$dn} installation in <strong>{$An}</strong> by NettingTeam means precision drilling, corrosion-resistant anchors, correct tensioning and a clean finish — completed in a single visit. Our installers carry all equipment for {$An} jobs: professional drills, SS anchor sets, tensioning tools, safety harnesses and all Russea™ materials from our Karmanghat warehouse. We do not send untrained helpers — every installation in {$An} is led by a certified technician with minimum 3 years experience.</p><p>Post-installation in {$An}: our technician performs a tension and load check, photographs the completed installation, hands over the 5-year warranty document and briefs you on the minimal maintenance required (zero routine maintenance for Russea™ UV Grade-A nets). Follow-up service calls for {$An} are handled within 24 hours under warranty. Call 9912399334 to book an installation in {$An} today.</p>",
"<p>When it comes to {$dn} installation in <strong>{$An}</strong>, NettingTeam is the benchmark in {$zd}. We have installed across all building categories — luxury apartments, builder floors, independent houses, commercial buildings and institutional properties. Our standardized installation protocol ensures identical quality whether we are working in {$An} or any other part of Hyderabad.</p><p>Our {$An} installation team is equipped with professional-grade tools and uses only Russea™ certified anchors and SS hardware — no cheap alternatives that corrode and loosen within months. After installation in {$An}, we conduct a pull-test check on anchor points and a visual inspection of every net seam before signing off. This is why our installed nets in {$An} never fail within the warranty period. 5-year warranty — call 9912399334.</p>",
"<p>NettingTeam brings certified {$dn} installation directly to <strong>{$An}</strong> — no travel charges, same-day availability for urgent jobs. Our Karmanghat base is positioned to cover {$An} and surrounding areas of {$n5} within rapid response time. Installation appointments are confirmed within 2 hours of your call, and our team arrives at the scheduled time with all materials pre-loaded.</p><p>Our installation process in {$An} is designed for minimal disruption to your daily routine: drilling is done with vacuum dust collectors, material is handled with protective floor sheets, and all debris is cleared before the team leaves. A typical {$An} balcony installation takes 2–4 hours including the post-installation check. Same-day installation is possible for most {$An} jobs — call 9912399334 before noon for same-day confirmation.</p>",
"<p>Five thousand completed installations across Hyderabad — and counting. NettingTeam brings the same certified installation standard to every property in <strong>{$An}</strong> regardless of building age, height or size. Our installation supervisor inspects every anchor point before signing off on any {$An} job. We do not accept incomplete work or leave a job until the customer is satisfied and the 5-year warranty document is in hand.</p><p>Installation for {$dn} in {$An} buildings uses corrosion-resistant SS anchors rated for marine environments — because Hyderabad monsoon humidity is as demanding as coastal conditions. These heavy-duty anchors ensure our {$An} installations remain firm for the full 10-year material life without loosening or corrosion. Inspection reports and photo documentation are provided after every installation in {$An}. Book via 9912399334.</p>",
];

$supply_body = [
"<p>As a direct Russea™ wholesale supplier for <strong>{$An}</strong>, NettingTeam provides {$dn} at factory-direct pricing to contractors, builders, housing societies and bulk buyers across {$zd}. We maintain the largest Russea™ stock in Hyderabad — available for same-day dispatch. Wholesale supply to {$An} customers includes proper GST invoices, Russea™ material certificates and flexible payment terms for large projects.</p><p>Contractors working in {$An} and surrounding {$n3} get dedicated account management from NettingTeam: reserved stock allocation, project-wise scheduling, and site delivery. Minimum wholesale order is 200 sqft — volume pricing tiers apply above 500 sqft, 2000 sqft and 5000 sqft. All Russea™ materials come with batch certificates and are covered by manufacturer warranty. Call 9912399334 for wholesale rate card.</p>",
"<p>NettingTeam supplies {$dn} wholesale to the entire {$zd} region, covering <strong>{$An}</strong> and surrounding localities of {$n5}. As Russea™ authorized wholesale distributor, we eliminate the middlemen that add 25–40% to your material cost. Same-day supply for stock items, next-day for custom specifications. Our {$An} wholesale supply covers nets, all hardware, anchors and accessories as a complete single-source supply chain.</p><p>Housing societies in {$An} procuring for multiple flats get project-based wholesale pricing from NettingTeam — typically 15–25% below per-unit retail rates for orders above 2000 sqft. We issue a single consolidated GST invoice covering all units, and coordinate installation scheduling floor-by-floor to minimize resident disruption. Call 9912399334 for {$An} housing society project pricing.</p>",
"<p>Contractors and builders in <strong>{$An}</strong> rely on NettingTeam as their preferred {$dn} supplier because we combine Russea™ manufacturer-certified quality with wholesale pricing and reliable on-time supply. Order today, dispatch today for all in-stock Russea™ products. GST invoices, material test certificates and packing slips are provided for every {$An} supply order — essential for site compliance and client documentation.</p><p>Our {$An} supply includes the full Russea™ product catalogue: HDPE nets (25mm to 75mm mesh), nylon sports nets, UV Grade-A balcony nets, invisible grill SS cables, ceiling hanger systems and all fixing accessories. A single purchase order with NettingTeam covers everything your project in {$An} needs — no running to multiple suppliers. Call 9912399334 for a supply quote.</p>",
"<p>The most reliable {$dn} supplier serving <strong>{$An}</strong> is NettingTeam — maintaining a full Russea™ product inventory at our Karmanghat warehouse. We supply retail customers (1 unit) and bulk buyers (10,000+ sqft projects) with equal efficiency and the same Russea™ quality standard. Our {$An} supply operations have zero delays on stock items — maintained at buffer stock levels to handle urgent project requirements in {$zd}.</p><p>Supply-only customers in {$An} (customers with their own installation teams) are equally welcome at NettingTeam. We sell wholesale material-only without requiring installation purchase. Material-only orders for {$An} are dispatched with complete installation guidance documents and technical support on call. Russea™ material certificates are provided with every supply, enabling builders to include them in project documentation. Call 9912399334.</p>",
"<p>NettingTeam is the wholesale {$dn} supplier trusted by builders, real estate developers and contractors across {$zd}, including <strong>{$An}</strong>. Volume-based pricing tiers reward larger orders: the more you buy, the lower your per-sqft cost, with maximum discount tiers at 5,000 sqft and above. All supply for {$An} orders comes with Russea™ material certificates, load test data sheets and manufacturer warranty documentation — essential for RERA compliance and project audits.</p><p>Our {$An} supply logistics: orders placed before noon are dispatched same day to any address in {$An} and nearby {$n3}. Bulk deliveries (1000+ sqft) include free unloading assistance. We also maintain a dedicated account for repeat {$An} contractor customers — priority stock allocation and 30-day credit terms for verified businesses. Call 9912399334 to open a supplier account.</p>",
];

$near_me_body = [
"<p>Searching for {$dn} near <strong>{$An}</strong>? NettingTeam is your nearest authorized Russea™ dealer — operating from Karmanghat, covering all of {$zd} with priority service to {$An}. Our team can reach your {$An} property for a free site visit the same day you call. Materials are in-stock and ready for immediate installation — no waiting for orders to arrive from distant suppliers.</p><p>NettingTeam covers {$An} and all nearby localities: {$n5}. Wherever you are in this area, our team is the closest authorized installer with genuine Russea™ materials. Walk-in customers from {$An} are welcome at our Karmanghat showroom to inspect stock, compare grades and get instant pricing. Call 9912399334 — response within minutes.</p>",
"<p>The nearest authorized {$dn} service to <strong>{$An}</strong> is NettingTeam at Karmanghat — strategically located to serve all of {$zd} with rapid response. We have a dedicated service zone covering {$An} with technicians who have completed multiple jobs in this area and are familiar with building types, access requirements and local installation conditions specific to {$An} properties.</p><p>For urgent {$dn} needs near {$An} — such as a damaged net or a child-safety concern — NettingTeam offers priority same-day service. Call 9912399334 and our nearest available technician for {$An} will be dispatched immediately. We also serve {$n5} from the same response zone. No appointment needed for emergency service requests.</p>",
"<p>NettingTeam near <strong>{$An}</strong> means: in-stock Russea™ {$dn}, certified installation team, same-day availability and the most competitive factory-direct pricing in {$zd}. Our physical store at Karmanghat is the only authorized Russea™ dealer outlet accessible to {$An} residents without a long drive. Walk in, see the stock, test the material strength, compare grades — then decide. No pressure sales, transparent pricing.</p><p>Why choose a dealer near {$An} matters: local dealers understand {$An} building types, HOA requirements, and the specific installation challenges of this locality. NettingTeam has installed in multiple properties near {$An} — our team knows the typical building profiles, floor heights and anchor requirements in this area of {$zd}. Accumulated local experience means faster, more reliable installations. Call 9912399334.</p>",
"<p>For customers in <strong>{$An}</strong> looking for {$dn} nearby, NettingTeam provides the fastest response in {$zd}. Our scheduling system allocates technicians by service zone — {$An} customers get allocated to a team already operating in {$zd} for minimal travel time and same-day availability. Materials are pre-loaded from our Karmanghat stock, so no delays between booking and installation in {$An}.</p><p>Nearby areas we regularly service from the same {$An} zone: {$n5}. All these areas get the same rapid-response service as {$An} itself. If you are in any of these localities, NettingTeam is your nearest authorized Russea™ dealer and installer. Walk in at Karmanghat or call 9912399334 — our team coordinates {$An} area appointments on the same call.</p>",
"<p>NettingTeam near <strong>{$An}</strong>: ready stock, certified team, same-day service. Our Karmanghat location puts us at the centre of our service zone covering {$An} and all surrounding {$zd} localities. Every {$dn} call from {$An} is handled by a dedicated area coordinator who knows the locality, common building types and fastest route to your property.</p><p>We have completed {$dn} installations in most major apartment complexes and residential layouts near {$An}. Our technicians are familiar with the specific RCC floor and wall construction common in {$An} buildings — ensuring correct anchor depth and no structural surprises during installation. This hyperlocal knowledge, combined with Russea™ certified materials, makes NettingTeam the best near-me option for {$An}. Call 9912399334.</p>",
];

$wholsale_body = [
"<p>NettingTeam operates as the leading wholesale distributor of {$dn} in <strong>{$An}</strong> and across {$zd}. As a Russea™ authorized wholesale partner, we supply contractors, sub-contractors, real estate developers, builder groups and housing societies at distributor-level pricing. Our {$An} wholesale operations have zero minimum order restrictions for Russea™ products — buy 1 roll or 1,000 rolls at the same consistent quality.</p><p>Wholesale benefits for {$An} contractors: dedicated account manager, project-priority stock reservation, site-delivery scheduling, GST invoices and material certificates for all batches, and 30-day credit terms for established accounts. Our {$An} wholesale price list is updated monthly and shared with registered contractor accounts. Call 9912399334 to register as a wholesale account from {$An}.</p>",
"<p>The wholesale {$dn} market in <strong>{$An}</strong> is dominated by NettingTeam — the only Russea™ authorized wholesaler covering all of {$zd}. We supply bulk quantities at factory-direct pricing, enabling contractors in {$An} to price projects competitively while maintaining premium Russea™ material quality. Our wholesale supply chain has zero intermediaries between Russea™ factory and your {$An} job site.</p><p>Wholesale pricing tiers for {$An}: retail pricing applies up to 200 sqft; first wholesale tier applies from 200–1,000 sqft; project pricing from 1,000–5,000 sqft; and large-project rates for 5,000 sqft and above. At each tier, per-sqft cost decreases significantly. {$An} contractors working on multiple buildings simultaneously qualify for our project account pricing — call 9912399334 for your tier pricing sheet.</p>",
"<p>Builders and developers operating in <strong>{$An}</strong> can benefit from NettingTeam' wholesale program for {$dn} — which includes not just competitive pricing but complete project support: material scheduling, installation crew coordination, quality certificates and post-installation warranty management. Our {$An} wholesale program is designed for multi-unit residential projects where consistent quality across all units is critical.</p><p>Our {$An} wholesale fulfillment: orders confirmed by 12 noon are dispatched same day; larger bulk orders (above 5,000 sqft) are dispatched within 24 hours from our Karmanghat warehouse. All wholesale dispatches to {$An} include packing slips with batch numbers, enabling project managers to track materials per floor or per unit. GST invoices with HSN codes provided for all wholesale purchases. Call 9912399334 for wholesale rates.</p>",
"<p>NettingTeam runs a dedicated wholesale supply operation for <strong>{$An}</strong> and surrounding {$n5} — providing Russea™ {$dn} to the construction supply chain at distributor prices. Our wholesale inventory is maintained at constant buffer stock levels so that urgent project requirements in {$An} can be fulfilled immediately without lead time delays. We have never failed to fulfil a confirmed wholesale order from {$An} on the promised date.</p><p>Wholesale purchase process for {$An}: (1) Register as a wholesale account via phone (9912399334); (2) Receive our current price list with all tiers; (3) Place orders via phone or WhatsApp; (4) Confirm delivery address in {$An} or pickup at Karmanghat; (5) Material dispatched with full documentation. Credit terms available after 3 successful transactions. All Russea™ materials carry full manufacturer warranty on wholesale purchases.</p>",
"<p>The most cost-effective way for contractors in <strong>{$An}</strong> to procure {$dn} is through NettingTeam' wholesale program — which combines factory-direct pricing, project-scheduling support and post-delivery technical assistance. Unlike buying from local dealers who add multiple markup layers, NettingTeam wholesale pricing in {$An} reflects actual factory cost plus minimal dealer margin. For a typical 500-unit housing project in {$An}, our wholesale pricing saves Rs. 2–3 lakh compared to retail market rates.</p><p>Our {$An} wholesale account holders receive: monthly price updates, priority dispatch during peak season (October–February), dedicated WhatsApp support for order tracking, and site visits for quality verification on large projects. NettingTeam has supplied over 50 major residential projects across {$zd} including multiple premium developments in {$An} area. Call 9912399334 to discuss your {$An} project requirements.</p>",
];

$mfr_body = [
"<p>NettingTeam supplies {$dn} in <strong>{$An}</strong> directly from Russea™ — Hyderabad's #1 ISO 9001:2015 certified net manufacturer. Russea™ operates state-of-the-art manufacturing with UV Grade-A stabilizers tested per IS:14453, breaking strength verified per batch, and eco-friendly recyclable HDPE yarn. As the authorized dealer in {$An}, NettingTeam is the shortest path between Russea™ factory and your property.</p><p>What manufacturer-direct means for {$An} customers: zero counterfeit risk, traceable batch codes, material test certificates on request, and full manufacturer warranty (not just dealer warranty). When you buy {$dn} from NettingTeam in {$An}, you are buying the same quality that large infrastructure projects and government contracts specify. Call 9912399334 for manufacturer-backed pricing in {$An}.</p>",
"<p>The Russea™ manufacturing process that produces the {$dn} installed in <strong>{$An}</strong> properties includes UV stabilizer injection at yarn extrusion stage, knotting/knotless weave per application, load testing at 200 kg minimum, and salt-spray corrosion testing for marine-grade ratings. This is not achievable by local small manufacturers in {$An} or elsewhere — only Russea™ ISO 9001:2015 certified factory can guarantee these specifications consistently.</p><p>NettingTeam, as the authorized Russea™ dealer in {$zd}, brings manufacturer-grade quality to every {$An} installation at prices accessible to individual homeowners. We show customers in {$An} the Russea™ batch certificate, ISO certification document and UV test report for every material batch — transparency that no unbranded local supplier can match. Call 9912399334 for manufacturer documentation in {$An}.</p>",
"<p>Russea™ manufacturer-certified {$dn} installed in <strong>{$An}</strong> properties by NettingTeam comes with a full chain of custody: factory batch → authorized dealer → certified installer → 5-year written warranty. This chain is unbroken and verifiable at every step. {$An} customers are protected against counterfeit materials, installation defects and premature failure — all covered under the Russea™ manufacturer-backed warranty.</p><p>The difference between Russea™ manufactured {$dn} and generic alternatives in {$An}: UV Grade-A stabilization (generic = UV-C rated, fails in 18 months), breaking strength 200+ kg (generic = 50–80 kg, not load-tested), corrosion-resistant hardware (generic = galvanized iron that rusts in 2 monsoons), and warranty-backed (generic = no warranty). NettingTeam sells only Russea™ in {$An} — no generics, no substitutes. Call 9912399334.</p>",
"<p>NettingTeam serves as the direct manufacturer representative for Russea™ {$dn} in <strong>{$An}</strong> — which means {$An} customers get factory technical support, not just dealer support. When you have a technical question about mesh specification, load rating or installation method for your {$An} property, our team can connect you directly with Russea™ technical engineers. This level of manufacturer support is unavailable from any other dealer in {$zd}.</p><p>Russea™ manufacturing standards applied to every batch sold in {$An}: ISO 9001:2015 quality management, UV Grade-A per IS:14453, breaking strength ≥ 200 kg/m², recyclable HDPE with RoHS compliance, and zero heavy-metal additives. These are the standards that matter when installing safety nets for children and pets in {$An} homes — and NettingTeam will not compromise them for price. Call 9912399334.</p>",
"<p>For {$An} customers who want manufacturer-quality {$dn} without manufacturer-scale minimum orders, NettingTeam is the answer. As Russea™ authorized dealer in {$zd}, we make manufacturer-certified quality accessible for single-balcony orders in {$An} — not just bulk project orders. Whether you need 50 sqft or 50,000 sqft, you get the same ISO 9001:2015 certified Russea™ materials with the same manufacturer warranty.</p><p>Russea™ factory supplies to major construction projects, government buildings and international installations — the same production line that produces materials for these projects is what NettingTeam brings to individual homeowners in {$An}. There is no quality difference between a bulk order and a single-balcony order from NettingTeam in {$An} — Russea™ does not produce different grades for different order sizes. Call 9912399334 for {$An} pricing.</p>",
];

$rate_body = [
"<p>The rate for {$dn} in <strong>{$An}</strong> is best understood as a comprehensive value — not just a per-sqft number. NettingTeam provides an all-inclusive rate that covers Russea™ certified materials, professional installation, corrosion-resistant anchors, hardware, 5-year warranty and post-installation service calls within warranty. No separate material charge, no separate labor charge, no separate warranty fee. One rate, everything included for {$An} customers.</p><p>Rate benchmarks for {$An}: starting from Rs. {$s['price']} for standard specifications. Economy-of-scale rate reductions apply for orders above 500 sqft — making bulk or multi-floor orders in {$An} progressively more cost-effective. Transparent, itemized rate breakdowns are provided in every written quote. Call 9912399334 for your {$An} rate estimate — free, no-obligation, provided within 24 hours of site visit.</p>",
"<p>NettingTeam maintains consistent, transparent rates for {$dn} in <strong>{$An}</strong> and across {$zd}. Our rate policy: (1) Written quote before work begins — never verbal; (2) Rate is fixed from quote to invoice — no escalation; (3) All-inclusive — materials, labor, hardware and warranty included; (4) No floor-level premium up to 10th floor; (5) Bulk discount tiers above 500 sqft for {$An} orders. This rate transparency policy has made NettingTeam the most trusted {$dn} provider in {$zd}.</p><p>Rate comparison for {$An}: our Russea™ factory-direct sourcing saves 15–25% over competitors who purchase through distributors. For a typical 200 sqft balcony in {$An}, this translates to Rs. 2,000–4,000 in savings with superior warranty-backed materials. Request your written rate estimate for {$An} today — call 9912399334 or walk in at Karmanghat.</p>",
"<p>Understanding {$dn} rates in <strong>{$An}</strong>: NettingTeam prices reflect three key factors — material quality (Russea™ Grade-A only), installation expertise (certified 10+ year team), and warranty coverage (5 years written). Competitors in {$An} who quote lower rates are invariably cutting corners on one or more of these three. We have seen {$An} customers return after cheap installations fail within 12–18 months — and the re-installation cost far exceeds what the initial saving was.</p><p>NettingTeam rate assurance for {$An}: once your written quote is issued, the rate is locked for 30 days. If material costs change in that window, we absorb the change — not the {$An} customer. This rate-lock policy is unique to NettingTeam in {$zd} and is part of our commitment to fair, transparent pricing for {$An} property owners. Call 9912399334 for your locked-rate quote.</p>",
"<p>The rate for {$dn} varies across {$An} only by property specifications — not by locality or building prestige. NettingTeam charges the same rate for a premium apartment in the heart of {$An} as for a modestly-priced building on the outskirts — we do not adjust rates based on perceived customer ability to pay. This consistent, fair-rate policy has earned NettingTeam a reputation for integrity across {$zd} including all of {$An}.</p><p>Rate factors that DO affect your {$An} quote: (1) Area size in sqft — the primary rate driver; (2) Material grade chosen — standard or premium UV Grade-A; (3) Building age — older buildings sometimes need heavier anchors at slight extra cost; (4) High-rise (above 10th floor) — minor safety-equipment surcharge. All four factors are clearly explained and itemized in every {$An} written quote. No hidden rate factors. Call 9912399334.</p>",
"<p>For <strong>{$An}</strong> customers comparing {$dn} rates across providers, NettingTeam invites head-to-head rate comparison. Call us (9912399334) and any competitor, share identical specifications, and compare the written quotes. In most cases, our {$An} rate is equal to or lower than competitors — while our material quality (Russea™ ISO certified) and warranty terms (5-year written) are superior. Factory-direct sourcing is the rate advantage that no distributor-dependent competitor can match in {$zd}.</p><p>Our {$An} rate covers the complete lifecycle value of your {$dn} installation: 7–10 year material life, 5-year warranty, zero maintenance cost, and free inspection calls within warranty period. When you amortize our {$An} rate over the product lifespan, NettingTeam delivers the lowest cost-per-year of any {$dn} provider in {$zd}. The rate you pay today buys a decade of protection. Call 9912399334 for your {$An} estimate.</p>",
];

// ── SELECT VARIANT ─────────────────────────────────────────────────────────
$d_body  = $dealer_body[$v];
$p_body  = $price_body[$v];
$i_body  = $install_body[$v];
$s_body  = $supply_body[$v];
$nm_body = $near_me_body[$v];
$w_body  = $wholsale_body[$v];
$m_body  = $mfr_body[$v];
$r_body  = $rate_body[$v];
?>

<section id="dealers" class="section" style="background:#f8fafc"><div class="container">
<h2 class="section-title">Authorized <?=$dn?> Dealers in <?=$An?></h2>
<?=$d_body?>
<div style="background:#f0fdf4;border-left:4px solid #16a34a;padding:1rem 1.25rem;border-radius:6px;margin-top:1rem">
<strong>Walk-in Welcome:</strong> NettingTeam showroom, Karmanghat, Hyderabad — serving all of India. Inspect Russea™ stock, verify certificates, buy retail or wholesale. Call <a href="tel:+919912399334" style="color:#15803d;font-weight:700">9912399334</a>.
</div></div></section>

<section id="price" class="section" style="background:white"><div class="container">
<h2 class="section-title"><?=$dn?> Price in <?=$An_city?></h2>
<?=$p_body?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-top:1.5rem">
<div style="background:#fefce8;border:2px solid #ca8a04;border-radius:10px;padding:1rem;text-align:center"><strong>Starting From</strong><br><span style="font-size:1.5rem;font-weight:800;color:#92400e">Rs. <?=htmlspecialchars($s['price'])?></span><br><small>All-inclusive in <?=$An?></small></div>
<div style="background:#eff6ff;border:2px solid #2563eb;border-radius:10px;padding:1rem;text-align:center"><strong>Free Site Estimate</strong><br><span style="font-size:1.1rem;font-weight:700;color:#1d4ed8">No Obligation</span><br><small>Written quote in 24 hrs</small></div>
<div style="background:#f0fdf4;border:2px solid #16a34a;border-radius:10px;padding:1rem;text-align:center"><strong>Price Lock</strong><br><span style="font-size:1.1rem;font-weight:700;color:#15803d">30-Day Valid</span><br><small>Quote = Final invoice price</small></div>
<div style="background:#fdf4ff;border:2px solid #9333ea;border-radius:10px;padding:1rem;text-align:center"><strong>Bulk Discount</strong><br><span style="font-size:1.1rem;font-weight:700;color:#7e22ce">Above 500 sqft</span><br><small>Volume tiers available</small></div>
</div></div></section>

<section id="installation" class="section" style="background:#f8fafc"><div class="container">
<h2 class="section-title"><?=$dn?> Installation in <?=$An_city?></h2>
<?=$i_body?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:1.5rem">
<div style="text-align:center;padding:1.2rem;background:white;border-radius:10px;border:1px solid #e5e7eb"><i class="fas fa-clock" style="color:#2563eb;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>2–4 Hours</strong><br><small>Typical installation time</small></div>
<div style="text-align:center;padding:1.2rem;background:white;border-radius:10px;border:1px solid #e5e7eb"><i class="fas fa-hard-hat" style="color:#16a34a;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>Certified Team</strong><br><small>10+ years experience</small></div>
<div style="text-align:center;padding:1.2rem;background:white;border-radius:10px;border:1px solid #e5e7eb"><i class="fas fa-shield-alt" style="color:#dc2626;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>5-Year Warranty</strong><br><small>Written from day one</small></div>
</div></div></section>

<section id="suppliers" class="section" style="background:white"><div class="container">
<h2 class="section-title"><?=$dn?> Suppliers &amp; Wholesale in <?=$An?></h2>
<?=$s_body?>
</div></section>

<section id="near-me" class="section" style="background:#f8fafc"><div class="container">
<h2 class="section-title"><?=$dn?> Near <?=$An?> — Immediate Service</h2>
<?=$nm_body?>
</div></section>

<section id="wholesalers" class="section" style="background:white"><div class="container">
<h2 class="section-title"><?=$dn?> Wholesalers in <?=$An?></h2>
<?=$w_body?>
</div></section>

<section id="manufacturers" class="section" style="background:#f8fafc"><div class="container">
<h2 class="section-title"><?=$dn?> Manufacturers — Russea™ Direct in <?=$An?></h2>
<?=$m_body?>
</div></section>

<section id="rate" class="section" style="background:white"><div class="container">
<h2 class="section-title"><?=$dn?> Rate in <?=$An_city?></h2>
<?=$r_body?>
<div style="margin-top:1.5rem;text-align:center">
<a href="tel:+919912399334" class="btn btn-primary btn-lg"><i class="fas fa-phone"></i> Get Rate for <?=$An?> — 9912399334</a>
</div></div></section>

<section class="section" style="background:var(--color-gray-50)"><div class="container">
<h2 class="section-title">Frequently Asked Questions &mdash; <?=htmlspecialchars($A)?></h2>
<div class="grid grid-2" style="gap:1.5rem"><?=$faq_html?></div>
</div></section>

<section class="section" style="background:white"><div class="container">
<h2 class="section-title">Dealers, Suppliers &amp; Installation Experts in <?=htmlspecialchars($A)?></h2>
<p style="text-align:center;max-width:680px;margin:0 auto 2rem;color:#4b5563">NettingTeam serves <?=htmlspecialchars($A)?> as an authorized dealer, direct wholesale supplier, and certified installation expert — all under one roof. Retail customers and bulk wholesale buyers are both welcome.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:2.5rem">
<div style="background:#f0fdf4;border:2px solid #16a34a;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-store" style="color:#16a34a;font-size:1.8rem;margin-bottom:0.5rem"></i><br><strong>Authorized Dealer</strong><br><small>Russea™ official dealer in <?=htmlspecialchars($A)?></small></div>
<div style="background:#eff6ff;border:2px solid #2563eb;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-boxes" style="color:#2563eb;font-size:1.8rem;margin-bottom:0.5rem"></i><br><strong>Wholesale Supplier</strong><br><small>Bulk orders welcome — factory prices</small></div>
<div style="background:#fefce8;border:2px solid #ca8a04;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-industry" style="color:#ca8a04;font-size:1.8rem;margin-bottom:0.5rem"></i><br><strong>Manufacturer Direct</strong><br><small>Russea™ ISO 9001:2015 certified manufacturer</small></div>
<div style="background:#fdf4ff;border:2px solid #9333ea;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-hard-hat" style="color:#9333ea;font-size:1.8rem;margin-bottom:0.5rem"></i><br><strong>Installation Experts</strong><br><small>Trained &amp; certified installation team</small></div>
<div style="background:#fff1f2;border:2px solid #e11d48;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-tags" style="color:#e11d48;font-size:1.8rem;margin-bottom:0.5rem"></i><br><strong>Retail Available</strong><br><small>Walk-in welcome — inspect before you buy</small></div>
<div style="background:#f0fdfa;border:2px solid #0d9488;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-truck" style="color:#0d9488;font-size:1.8rem;margin-bottom:0.5rem"></i><br><strong>Immediate Dispatch</strong><br><small>In-stock materials — same-day delivery possible</small></div>
</div>
</div></section>

<section class="section"><div class="container">
<h2 class="section-title">We Also Serve Areas Near <?=htmlspecialchars($A)?></h2>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.75rem;margin-bottom:2rem"><?=$nearby_html?></div>
<div class="text-center">
<a href="tel:+919912399334" class="btn btn-primary btn-lg"><i class="fas fa-phone"></i> Book in <?=htmlspecialchars($A)?> &mdash; 9912399334</a>
<a href="contact.php" class="btn btn-secondary btn-lg" style="margin-left:1rem"><i class="fas fa-comment-dots"></i> Get Free Quote</a>
</div></div></section>

<section class="section" style="background:var(--color-gray-50)"><div class="container">
<h2 class="section-title"><?=htmlspecialchars($s['display'])?> — All Service Areas in <?=htmlspecialchars($city_name)?></h2>
<p style="text-align:center;max-width:680px;margin:0 auto 2rem;color:#4b5563">NettingTeam provides <?=strtolower(htmlspecialchars($s['display']))?> across all major areas, localities and neighbourhoods of <?=htmlspecialchars($city_name)?>. Click your area below to see pricing, specifications and installation details.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.6rem">
<?php foreach($areas as $aslug=>$adata): ?>
<a href="/<?=htmlspecialchars($sv)?>-in-<?=htmlspecialchars($aslug)?>" style="display:block;padding:0.5rem 0.75rem;background:white;border:1px solid #e5e7eb;border-radius:8px;color:#374151;text-decoration:none;font-size:0.82rem;font-weight:500;transition:all 0.15s" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'" onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151'"><i class="fas fa-map-marker-alt" style="color:#dc2626;margin-right:4px;font-size:0.75rem"></i><?=htmlspecialchars($adata['label'])?></a>
<?php endforeach; ?>
</div>
</div></section>
<?php include 'includes/footer.php'; ?>
