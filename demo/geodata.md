# Bangladesh Geo Reference Data

Source: `assets/js/bd-geo-data.json` — used by the student add/edit forms for the cascading Division → District → Upazila dropdowns. The Zip Code (postal code) is auto-filled from the selected upazila.

Totals: **8 Divisions** — **64 Districts** — **494 Upazilas** (each with postal/zip code).

## Student Identity Documents

Stored on the student record and shown in the Identity Documents card on the profile page:

| Field | Max length | Purpose |
|-------|-----------|---------|
| Birth Certificate No. (`birth_cert_number`) | 30 | Bangladesh birth certificate number |
| National ID No. (`nid_number`) | 30 | NID number (photo card / smart card) |
| Passport Number (`passport_number`) | 40 | Machine-readable passport number |
| Photo | — | Passport-size photo, JPG/PNG ≤ 5 MB, resized to ~400×500 and ~100 KB |

Examples used in the demo pages: Birth Cert. `2000112233445566`, NID `1999887654321`, Passport `AA1234567`.

## Divisions

| ID | Name (EN) | Name (BN) |
|----|-----------|-----------|
| 3 | Khulna | খুলনা |
| 1 | Chattagram | চট্টগ্রাম |
| 6 | Dhaka | ঢাকা |
| 4 | Barisal | বরিশাল |
| 8 | Mymensingh | ময়মনসিংহ |
| 7 | Rangpur | রংপুর |
| 2 | Rajshahi | রাজশাহী |
| 5 | Sylhet | সিলেট |

## Division: Chattagram (ID 1) চট্টগ্রাম

### Coxsbazar (ID 9) কক্সবাজার

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Eidgaon | ঈদগাঁও | 492 | 4702 |
| Ukhiya | উখিয়া | 83 | 4750 |
| Coxsbazar Sadar | কক্সবাজার সদর | 80 | 4700 |
| Kutubdia | কুতুবদিয়া | 82 | 4720 |
| Chakaria | চকরিয়া | 81 | 4740 |
| Teknaf | টেকনাফ | 87 | 4760 |
| Pekua | পেকুয়া | 85 | 4770 |
| Moheshkhali | মহেশখালী | 84 | 4700 |
| Ramu | রামু | 86 | 4730 |

### Comilla (ID 1) কুমিল্লা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Comilla Sadar | কুমিল্লা সদর | 11 | 3500 |
| Chandina | চান্দিনা | 4 | 3510 |
| Chauddagram | চৌদ্দগ্রাম | 5 | 3550 |
| Titas | তিতাস | 15 | 3547 |
| Daudkandi | দাউদকান্দি | 6 | 3516 |
| Debidwar | দেবিদ্বার | 1 | 3531 |
| Nangalkot | নাঙ্গলকোট | 10 | 3580 |
| Barura | বরুড়া | 2 | 3560 |
| Burichang | বুড়িচং | 16 | 3520 |
| Brahmanpara | ব্রাহ্মণপাড়া | 3 | 3526 |
| Monohargonj | মনোহরগঞ্জ | 13 | 3562 |
| Muradnagar | মুরাদনগর | 9 | 3540 |
| Meghna | মেঘনা | 12 | 3515 |
| Laksam | লাকসাম | 8 | 3570 |
| Lalmai | লালমাই | 17 | 3573 |
| Sadarsouth | সদর দক্ষিণ | 14 | 3500 |
| Homna | হোমনা | 7 | 3546 |

### Khagrachhari (ID 10) খাগড়াছড়ি

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Khagrachhari Sadar | খাগড়াছড়ি সদর | 88 | 4400 |
| Guimara | গুইমারা | 96 | 4440 |
| Dighinala | দিঘীনালা | 89 | 4420 |
| Panchari | পানছড়ি | 90 | 4410 |
| Mohalchari | মহালছড়ি | 92 | 4430 |
| Matiranga | মাটিরাঙ্গা | 95 | 4450 |
| Manikchari | মানিকছড়ি | 93 | 4460 |
| Ramgarh | রামগড় | 94 | 3721 |
| Laxmichhari | লক্ষীছড়ি | 91 | 4470 |

### Chattogram (ID 8) চট্টগ্রাম

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Anwara | আনোয়ারা | 72 | 4376 |
| Karnafuli | কর্ণফুলী | 79 | 4000 |
| Chandanaish | চন্দনাইশ | 73 | 4383 |
| Patiya | পটিয়া | 68 | 3891 |
| Fatikchhari | ফটিকছড়ি | 77 | 4350 |
| Banshkhali | বাঁশখালী | 70 | 4393 |
| Boalkhali | বোয়ালখালী | 71 | 4366 |
| Mirsharai | মীরসরাই | 67 | 4320 |
| Raozan | রাউজান | 78 | 4340 |
| Rangunia | রাঙ্গুনিয়া | 65 | 4360 |
| Lohagara | লোহাগাড়া | 75 | 4396 |
| Sandwip | সন্দ্বীপ | 69 | 4300 |
| Satkania | সাতকানিয়া | 74 | 4386 |
| Sitakunda | সীতাকুন্ড | 66 | 4310 |
| Hathazari | হাটহাজারী | 76 | 4330 |

### Chandpur (ID 6) চাঁদপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kachua | কচুয়া | 53 | 3630 |
| Chandpur Sadar | চাঁদপুর সদর | 55 | 3600 |
| Faridgonj | ফরিদগঞ্জ | 59 | 3650 |
| Matlab North | মতলব উত্তর | 58 | 3640 |
| Matlab South | মতলব দক্ষিণ | 56 | 3517 |
| Shahrasti | শাহরাস্তি	 | 54 | 3620 |
| Haimchar | হাইমচর | 52 | 3660 |
| Hajiganj | হাজীগঞ্জ | 57 | 3610 |

### Noakhali (ID 5) নোয়াখালী

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kabirhat | কবিরহাট | 48 | 3807 |
| Companiganj | কোম্পানীগঞ্জ | 44 | 3140 |
| Chatkhil | চাটখিল | 50 | 3870 |
| Noakhali Sadar | নোয়াখালী সদর | 43 | 3800 |
| Begumganj | বেগমগঞ্জ | 45 | 3820 |
| Subarnachar | সুবর্ণচর | 47 | 3812 |
| Senbug | সেনবাগ | 49 | 3860 |
| Sonaimori | সোনাইমুড়ী | 51 | 3827 |
| Hatia | হাতিয়া | 46 | 3890 |

### Feni (ID 2) ফেনী

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Chhagalnaiya | ছাগলনাইয়া | 18 | 3910 |
| Daganbhuiyan | দাগনভূঞা | 23 | 3923 |
| Parshuram | পরশুরাম | 22 | 3940 |
| Fulgazi | ফুলগাজী | 21 | 3942 |
| Feni Sadar | ফেনী সদর | 19 | 3900 |
| Sonagazi | সোনাগাজী | 20 | 3930 |

### Bandarban (ID 11) বান্দরবান

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Alikadam | আলীকদম | 98 | 4650 |
| Thanchi | থানচি | 103 | 4630 |
| Naikhongchhari | নাইক্ষ্যংছড়ি | 99 | 4660 |
| Bandarban Sadar | বান্দরবান সদর | 97 | 4600 |
| Ruma | রুমা | 102 | 4620 |
| Rowangchhari | রোয়াংছড়ি | 100 | 4610 |
| Lama | লামা | 101 | 4641 |

### Brahmanbaria (ID 3) ব্রাহ্মণবাড়িয়া

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Akhaura | আখাউড়া | 29 | 3450 |
| Ashuganj | আশুগঞ্জ | 28 | 3402 |
| Kasba | কসবা | 25 | 3460 |
| Nabinagar | নবীনগর | 30 | 3410 |
| Nasirnagar | নাসিরনগর | 26 | 3440 |
| Bancharampur | বাঞ্ছারামপুর | 31 | 3420 |
| Bijoynagar | বিজয়নগর | 32 | 3470 |
| Brahmanbaria Sadar | ব্রাহ্মণবাড়িয়া সদর | 24 | 3400 |
| Sarail | সরাইল | 27 | 3430 |

### Rangamati (ID 4) রাঙ্গামাটি

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kawkhali | কাউখালী | 35 | 4510 |
| Kaptai | কাপ্তাই | 34 | 4530 |
| Juraichari | জুরাছড়ি | 41 | 4560 |
| Naniarchar | নানিয়ারচর | 42 | 2341 |
| Barkal | বরকল | 37 | 4570 |
| Baghaichari | বাঘাইছড়ি | 36 | 4550 |
| Belaichari | বিলাইছড়ি | 40 | 4550 |
| Rangamati Sadar | রাঙ্গামাটি সদর | 33 | 4500 |
| Rajasthali | রাজস্থলী | 39 | 4540 |
| Langadu | লংগদু | 38 | 4580 |

### Lakshmipur (ID 7) লক্ষ্মীপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kamalnagar | কমলনগর | 61 | 3700 |
| Ramganj | রামগঞ্জ | 64 | 3720 |
| Ramgati | রামগতি | 63 | 3720 |
| Raipur | রায়পুর | 62 | 3710 |
| Lakshmipur Sadar | লক্ষ্মীপুর সদর | 60 | 3700 |

## Division: Rajshahi (ID 2) রাজশাহী

### Chapainawabganj (ID 18) চাঁপাইনবাবগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Gomostapur | গোমস্তাপুর | 156 | 6310 |
| Chapainawabganj Sadar | চাঁপাইনবাবগঞ্জ সদর | 155 | 6300 |
| Nachol | নাচোল | 157 | 6311 |
| Bholahat | ভোলাহাট | 158 | 6330 |
| Shibganj | শিবগঞ্জ | 159 | 5810 |

### Joypurhat (ID 17) জয়পুরহাট

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Akkelpur | আক্কেলপুর | 150 | 5940 |
| Kalai | কালাই | 151 | 5930 |
| Khetlal | ক্ষেতলাল | 152 | 5920 |
| Joypurhat Sadar | জয়পুরহাট সদর | 154 | 5900 |
| Panchbibi | পাঁচবিবি | 153 | 5910 |

### Naogaon (ID 19) নওগাঁ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Atrai | আত্রাই | 166 | 6596 |
| Dhamoirhat | ধামইরহাট | 163 | 6580 |
| Naogaon Sadar | নওগাঁ সদর | 168 | 6500 |
| Niamatpur | নিয়ামতপুর | 164 | 6520 |
| Patnitala | পত্নিতলা | 162 | 6540 |
| Porsha | পোরশা | 169 | 6551 |
| Badalgachi | বদলগাছী | 161 | 6570 |
| Mohadevpur | মহাদেবপুর | 160 | 6530 |
| Manda | মান্দা | 165 | 6511 |
| Raninagar | রাণীনগর | 167 | 6590 |
| Sapahar | সাপাহার | 170 | 6560 |

### Natore (ID 16) নাটোর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Gurudaspur | গুরুদাসপুর | 148 | 6440 |
| Naldanga | নলডাঙ্গা | 149 | 7351 |
| Natore Sadar | নাটোর সদর | 143 | 6400 |
| Baraigram | বড়াইগ্রাম | 145 | 6432 |
| Bagatipara | বাগাতিপাড়া | 146 | 6410 |
| Lalpur | লালপুর | 147 | 6421 |
| Singra | সিংড়া | 144 | 6450 |

### Pabna (ID 13) পাবনা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Atghoria | আটঘরিয়া | 118 | 6610 |
| Ishurdi | ঈশ্বরদী | 114 | 6620 |
| Chatmohar | চাটমোহর | 119 | 6630 |
| Pabna Sadar | পাবনা সদর | 116 | 6600 |
| Faridpur | ফরিদপুর | 121 | 5310 |
| Bera | বেড়া | 117 | 6680 |
| Bhangura | ভাঙ্গুড়া | 115 | 6640 |
| Santhia | সাঁথিয়া | 120 | 6670 |
| Sujanagar | সুজানগর | 113 | 6660 |

### Bogura (ID 14) বগুড়া

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Adamdighi | আদমদিঘি | 127 | 5890 |
| Kahaloo | কাহালু | 122 | 5870 |
| Gabtali | গাবতলী | 131 | 5820 |
| Dupchanchia | দুপচাচিঁয়া | 126 | 5880 |
| Dhunot | ধুনট | 130 | 5850 |
| Nondigram | নন্দিগ্রাম | 128 | 5860 |
| Bogra Sadar | বগুড়া সদর | 123 | 5800 |
| Shajahanpur | শাজাহানপুর | 125 | 5801 |
| Shibganj | শিবগঞ্জ | 133 | 5810 |
| Sherpur | শেরপুর | 132 | 5840 |
| Shariakandi | সারিয়াকান্দি | 124 | 5830 |
| Sonatala | সোনাতলা | 129 | 5826 |

### Rajshahi (ID 15) রাজশাহী

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Godagari | গোদাগাড়ী | 140 | 6290 |
| Charghat | চারঘাট | 137 | 6270 |
| Tanore | তানোর | 141 | 6230 |
| Durgapur | দুর্গাপুর | 135 | 6240 |
| Paba | পবা | 134 | 6210 |
| Puthia | পুঠিয়া | 138 | 6260 |
| Bagmara | বাগমারা | 142 | 6251 |
| Bagha | বাঘা | 139 | 6280 |
| Mohonpur | মোহনপুর | 136 | 6220 |

### Sirajganj (ID 12) সিরাজগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Ullapara | উল্লাপাড়া | 112 | 6760 |
| Kazipur | কাজীপুর | 107 | 6710 |
| Kamarkhand | কামারখন্দ | 106 | 6730 |
| Chauhali | চৌহালি | 105 | 1930 |
| Tarash | তাড়াশ | 111 | 6780 |
| Belkuchi | বেলকুচি | 104 | 6740 |
| Raigonj | রায়গঞ্জ | 108 | 6721 |
| Shahjadpur | শাহজাদপুর | 109 | 6770 |
| Sirajganj Sadar | সিরাজগঞ্জ সদর | 110 | 6700 |

## Division: Khulna (ID 3) খুলনা

### Kushtia (ID 25) কুষ্টিয়া

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kumarkhali | কুমারখালী | 197 | 7010 |
| Kushtia Sadar | কুষ্টিয়া সদর | 196 | 7000 |
| Khoksa | খোকসা | 198 | 7021 |
| Daulatpur | দৌলতপুর | 200 | 1860 |
| Bheramara | ভেড়ামারা | 201 | 7040 |
| Mirpur | মিরপুর | 199 | 7030 |

### Khulna (ID 27) খুলনা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Koyra | কয়রা | 214 | 9291 |
| Dumuria | ডুমুরিয়া | 211 | 9250 |
| Terokhada | তেরখাদা | 210 | 9230 |
| Dakop | দাকোপ | 213 | 9271 |
| Digholia | দিঘলিয়া | 208 | 9220 |
| Paikgasa | পাইকগাছা | 206 | 9280 |
| Fultola | ফুলতলা | 207 | 9210 |
| Botiaghata | বটিয়াঘাটা | 212 | 9260 |
| Rupsha | রূপসা | 209 | 9241 |

### Chuadanga (ID 24) চুয়াডাঙ্গা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Alamdanga | আলমডাঙ্গা | 193 | 7210 |
| Chuadanga Sadar | চুয়াডাঙ্গা সদর | 192 | 7200 |
| Jibannagar | জীবননগর | 195 | 7230 |
| Damurhuda | দামুড়হুদা | 194 | 7220 |

### Jhenaidah (ID 29) ঝিনাইদহ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kaliganj | কালীগঞ্জ | 227 | 1720 |
| Kotchandpur | কোটচাঁদপুর | 228 | 7330 |
| Jhenaidah Sadar | ঝিনাইদহ সদর | 224 | 7300 |
| Moheshpur | মহেশপুর | 229 | 7340 |
| Shailkupa | শৈলকুপা | 225 | 7320 |
| Harinakundu | হরিণাকুন্ডু | 226 | 7310 |

### Narail (ID 23) নড়াইল

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kalia | কালিয়া | 191 | 7520 |
| Narail Sadar | নড়াইল সদর | 189 | 7500 |
| Lohagara | লোহাগড়া | 190 | 7511 |

### Bagerhat (ID 28) বাগেরহাট

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kachua | কচুয়া | 221 | 9310 |
| Chitalmari | চিতলমারী | 223 | 9360 |
| Fakirhat | ফকিরহাট | 215 | 9370 |
| Bagerhat Sadar | বাগেরহাট সদর | 216 | 9300 |
| Mongla | মোংলা | 222 | 9350 |
| Mollahat | মোল্লাহাট | 217 | 9380 |
| Morrelganj | মোড়েলগঞ্জ | 220 | 9320 |
| Rampal | রামপাল | 219 | 9340 |
| Sarankhola | শরণখোলা | 218 | 9330 |

### Magura (ID 26) মাগুরা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Mohammadpur | মহম্মদপুর | 205 | 7630 |
| Magura Sadar | মাগুরা সদর | 204 | 7600 |
| Shalikha | শালিখা | 202 | 7620 |
| Sreepur | শ্রীপুর | 203 | 1743 |

### Meherpur (ID 22) মেহেরপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Gangni | গাংনী | 188 | 7110 |
| Mujibnagar | মুজিবনগর | 186 | 7100 |
| Meherpur Sadar | মেহেরপুর সদর | 187 | 7100 |

### Jashore (ID 20) যশোর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Abhaynagar | অভয়নগর | 172 | 7460 |
| Keshabpur | কেশবপুর | 176 | 7450 |
| Chougachha | চৌগাছা | 174 | 7410 |
| Jhikargacha | ঝিকরগাছা | 175 | 7420 |
| Bagherpara | বাঘারপাড়া | 173 | 7470 |
| Manirampur | মণিরামপুর | 171 | 7440 |
| Jessore Sadar | যশোর সদর | 177 | 7400 |
| Sharsha | শার্শা | 178 | 7430 |

### Satkhira (ID 21) সাতক্ষীরা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Assasuni | আশাশুনি | 179 | 9460 |
| Kalaroa | কলারোয়া | 181 | 9410 |
| Kaliganj | কালিগঞ্জ | 185 | 9440 |
| Tala | তালা | 184 | 9420 |
| Debhata | দেবহাটা | 180 | 9430 |
| Shyamnagar | শ্যামনগর | 183 | 9450 |
| Satkhira Sadar | সাতক্ষীরা সদর | 182 | 9400 |

## Division: Barisal (ID 4) বরিশাল

### Jhalakathi (ID 30) ঝালকাঠি

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kathalia | কাঠালিয়া | 231 | 8430 |
| Jhalakathi Sadar | ঝালকাঠি সদর | 230 | 8400 |
| Nalchity | নলছিটি | 232 | 8420 |
| Rajapur | রাজাপুর | 233 | 8410 |

### Patuakhali (ID 31) পটুয়াখালী

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kalapara | কলাপাড়া | 238 | 6762 |
| Galachipa | গলাচিপা | 240 | 8640 |
| Dashmina | দশমিনা | 237 | 8630 |
| Dumki | দুমকি | 236 | 8602 |
| Patuakhali Sadar | পটুয়াখালী সদর | 235 | 8600 |
| Bauphal | বাউফল | 234 | 8620 |
| Mirzaganj | মির্জাগঞ্জ | 239 | 8610 |
| Rangabali | রাঙ্গাবালী | 241 | 8640 |

### Pirojpur (ID 32) পিরোজপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kawkhali | কাউখালী | 244 | 8510 |
| Zianagar | জিয়ানগর | 245 | 8502 |
| Nazirpur | নাজিরপুর | 243 | 8540 |
| Nesarabad | নেছারাবাদ | 248 | 8522 |
| Pirojpur Sadar | পিরোজপুর সদর | 242 | 8500 |
| Bhandaria | ভান্ডারিয়া | 246 | 8550 |
| Mathbaria | মঠবাড়ীয়া | 247 | 8560 |

### Barguna (ID 35) বরগুনা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Amtali | আমতলী | 266 | 8710 |
| Taltali | তালতলি | 271 | 8710 |
| Pathorghata | পাথরঘাটা | 270 | 8720 |
| Barguna Sadar | বরগুনা সদর | 267 | 8700 |
| Bamna | বামনা | 269 | 8730 |
| Betagi | বেতাগী | 268 | 8740 |

### Barisal (ID 33) বরিশাল

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Agailjhara | আগৈলঝাড়া | 255 | 8240 |
| Wazirpur | উজিরপুর | 252 | 8220 |
| Gournadi | গৌরনদী | 254 | 8230 |
| Barisal Sadar | বরিশাল সদর | 249 | 8200 |
| Bakerganj | বাকেরগঞ্জ | 250 | 8432 |
| Banaripara | বানারীপাড়া | 253 | 8530 |
| Babuganj | বাবুগঞ্জ | 251 | 8210 |
| Muladi | মুলাদী | 257 | 8250 |
| Mehendiganj | মেহেন্দিগঞ্জ | 256 | 8270 |
| Hizla | হিজলা | 258 | 8250 |

### Bhola (ID 34) ভোলা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Charfesson | চরফ্যাশন | 261 | 8340 |
| Tazumuddin | তজুমদ্দিন | 264 | 8320 |
| Doulatkhan | দৌলতখান | 262 | 8310 |
| Borhanuddin | বোরহান উদ্দিন | 260 | 8320 |
| Bhola Sadar | ভোলা সদর | 259 | 8300 |
| Monpura | মনপুরা | 263 | 8360 |
| Lalmohan | লালমোহন | 265 | 8331 |

## Division: Sylhet (ID 5) সিলেট

### Moulvibazar (ID 37) মৌলভীবাজার

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kamolganj | কমলগঞ্জ | 286 | 3220 |
| Kulaura | কুলাউড়া | 287 | 3230 |
| Juri | জুড়ী | 291 | 3251 |
| Barlekha | বড়লেখা | 285 | 3250 |
| Moulvibazar Sadar | মৌলভীবাজার সদর | 288 | 3200 |
| Rajnagar | রাজনগর | 289 | 3240 |
| Sreemangal | শ্রীমঙ্গল | 290 | 3210 |

### Sylhet (ID 36) সিলেট

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Osmaninagar | ওসমানী নগর | 284 | 6591 |
| Kanaighat | কানাইঘাট | 280 | 3180 |
| Companiganj | কোম্পানীগঞ্জ | 275 | 3140 |
| Golapganj | গোলাপগঞ্জ | 277 | 3165 |
| Gowainghat | গোয়াইনঘাট | 278 | 3150 |
| Zakiganj | জকিগঞ্জ | 282 | 3190 |
| Jaintiapur | জৈন্তাপুর | 279 | 3156 |
| Dakshinsurma | দক্ষিণ সুরমা | 283 | 3106 |
| Fenchuganj | ফেঞ্চুগঞ্জ | 276 | 3116 |
| Balaganj | বালাগঞ্জ | 272 | 3120 |
| Bishwanath | বিশ্বনাথ | 274 | 3130 |
| Beanibazar | বিয়ানীবাজার | 273 | 3170 |
| Sylhet Sadar | সিলেট সদর | 281 | 3100 |

### Sunamganj (ID 39) সুনামগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Chhatak | ছাতক | 303 | 3080 |
| Jagannathpur | জগন্নাথপুর | 304 | 3060 |
| Jamalganj | জামালগঞ্জ | 308 | 3220 |
| Tahirpur | তাহিরপুর | 306 | 3030 |
| South Sunamganj | দক্ষিণ সুনামগঞ্জ | 301 | 3001 |
| Derai | দিরাই | 310 | 3002 |
| Dowarabazar | দোয়ারাবাজার | 305 | 3070 |
| Dharmapasha | ধর্মপাশা | 307 | 2450 |
| Bishwambarpur | বিশ্বম্ভরপুর | 302 | 3060 |
| Madhyanagar | মধ্যনগর | 493 | 3040 |
| Shalla | শাল্লা | 309 | 3050 |
| Sunamganj Sadar | সুনামগঞ্জ সদর | 300 | 3000 |

### Habiganj (ID 38) হবিগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Ajmiriganj | আজমিরীগঞ্জ | 294 | 3360 |
| Chunarughat | চুনারুঘাট | 297 | 3320 |
| Nabiganj | নবীগঞ্জ | 292 | 3370 |
| Baniachong | বানিয়াচং | 295 | 3350 |
| Bahubal | বাহুবল | 293 | 3310 |
| Madhabpur | মাধবপুর | 299 | 3330 |
| Lakhai | লাখাই | 296 | 3341 |
| Habiganj Sadar | হবিগঞ্জ সদর | 298 | 3300 |

## Division: Dhaka (ID 6) ঢাকা

### Kishoreganj (ID 45) কিশোরগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Austagram | অষ্টগ্রাম | 355 | 2380 |
| Itna | ইটনা | 345 | 2390 |
| Katiadi | কটিয়াদী | 346 | 2330 |
| Karimgonj | করিমগঞ্জ | 353 | 2310 |
| Kishoreganj Sadar | কিশোরগঞ্জ সদর | 352 | 2300 |
| Kuliarchar | কুলিয়ারচর | 351 | 2340 |
| Tarail | তাড়াইল | 348 | 2316 |
| Nikli | নিকলী | 357 | 2360 |
| Pakundia | পাকুন্দিয়া | 350 | 2326 |
| Bajitpur | বাজিতপুর | 354 | 2336 |
| Bhairab | ভৈরব | 347 | 2350 |
| Mithamoin | মিঠামইন | 356 | 2370 |
| Hossainpur | হোসেনপুর | 349 | 2320 |

### Gazipur (ID 41) গাজীপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kapasia | কাপাসিয়া | 319 | 1730 |
| Kaliakair | কালিয়াকৈর | 318 | 1750 |
| Kaliganj | কালীগঞ্জ | 317 | 1720 |
| Gazipur Sadar | গাজীপুর সদর | 320 | 1700 |
| Sreepur | শ্রীপুর | 321 | 1740 |

### Gopalganj (ID 51) গোপালগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kashiani | কাশিয়ানী | 386 | 8130 |
| Kotalipara | কোটালীপাড়া | 388 | 8110 |
| Gopalganj Sadar | গোপালগঞ্জ সদর | 385 | 8100 |
| Tungipara | টুংগীপাড়া | 387 | 8120 |
| Muksudpur | মুকসুদপুর | 389 | 8140 |

### Tangail (ID 44) টাঙ্গাইল

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kalihati | কালিহাতী | 343 | 1970 |
| Gopalpur | গোপালপুর | 337 | 1990 |
| Ghatail | ঘাটাইল | 336 | 1980 |
| Tangail Sadar | টাঙ্গাইল সদর | 342 | 1900 |
| Delduar | দেলদুয়ার | 335 | 1910 |
| Dhanbari | ধনবাড়ী | 344 | 1997 |
| Nagarpur | নাগরপুর | 340 | 1936 |
| Basail | বাসাইল | 333 | 1920 |
| Bhuapur | ভুয়াপুর | 334 | 1960 |
| Madhupur | মধুপুর | 338 | 1996 |
| Mirzapur | মির্জাপুর | 339 | 1940 |
| Sakhipur | সখিপুর | 341 | 1950 |

### Dhaka (ID 47) ঢাকা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Keraniganj | কেরাণীগঞ্জ | 367 | 1310 |
| Dohar | দোহার | 369 | 1330 |
| Dhamrai | ধামরাই | 366 | 1350 |
| Nawabganj | নবাবগঞ্জ | 368 | 1320 |
| Savar | সাভার | 365 | 1340 |

### Narsingdi (ID 40) নরসিংদী

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Narsingdi Sadar | নরসিংদী সদর | 313 | 1600 |
| Palash | পলাশ | 314 | 1610 |
| Belabo | বেলাবো | 311 | 1640 |
| Monohardi | মনোহরদী | 312 | 1650 |
| Raipura | রায়পুরা | 315 | 1630 |
| Shibpur | শিবপুর | 316 | 1620 |

### Narayanganj (ID 43) নারায়ণগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Araihazar | আড়াইহাজার | 328 | 1450 |
| Narayanganj Sadar | নারায়নগঞ্জ সদর | 330 | 1400 |
| Bandar | বন্দর | 329 | 1410 |
| Rupganj | রূপগঞ্জ | 331 | 1460 |
| Sonargaon | সোনারগাঁ | 332 | 1440 |

### Faridpur (ID 52) ফরিদপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Alfadanga | আলফাডাঙ্গা | 391 | 7870 |
| Charbhadrasan | চরভদ্রাসন | 396 | 7810 |
| Nagarkanda | নগরকান্দা | 394 | 7840 |
| Faridpur Sadar | ফরিদপুর সদর | 390 | 7800 |
| Boalmari | বোয়ালমারী | 392 | 7860 |
| Bhanga | ভাঙ্গা | 395 | 7830 |
| Madhukhali | মধুখালী | 397 | 7850 |
| Sadarpur | সদরপুর | 393 | 7820 |
| Saltha | সালথা | 398 | 7801 |

### Madaripur (ID 50) মাদারীপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kalkini | কালকিনি | 383 | 7920 |
| Dasar | ডাসার | 494 | 7900 |
| Madaripur Sadar | মাদারীপুর সদর | 381 | 7900 |
| Rajoir | রাজৈর | 384 | 7910 |
| Shibchar | শিবচর | 382 | 7930 |

### Manikganj (ID 46) মানিকগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Gior | ঘিওর | 361 | 1840 |
| Doulatpur | দৌলতপুর | 363 | 1860 |
| Manikganj Sadar | মানিকগঞ্জ সদর | 360 | 1800 |
| Shibaloy | শিবালয় | 362 | 1850 |
| Saturia | সাটুরিয়া | 359 | 1810 |
| Singiar | সিংগাইর | 364 | 1820 |
| Harirampur | হরিরামপুর | 358 | 7440 |

### Munshiganj (ID 48) মুন্সিগঞ্জ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Gajaria | গজারিয়া | 374 | 1510 |
| Tongibari | টংগীবাড়ি | 375 | 1520 |
| Munshiganj Sadar | মুন্সিগঞ্জ সদর | 370 | 1500 |
| Louhajanj | লৌহজং | 373 | 1530 |
| Sreenagar | শ্রীনগর | 371 | 1550 |
| Sirajdikhan | সিরাজদিখান | 372 | 1540 |

### Rajbari (ID 49) রাজবাড়ী

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kalukhali | কালুখালী | 380 | 8513 |
| Goalanda | গোয়ালন্দ | 377 | 7710 |
| Pangsa | পাংশা | 378 | 7720 |
| Baliakandi | বালিয়াকান্দি | 379 | 7730 |
| Rajbari Sadar | রাজবাড়ী সদর | 376 | 7700 |

### Shariatpur (ID 42) শরীয়তপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Gosairhat | গোসাইরহাট | 325 | 8050 |
| Zajira | জাজিরা | 324 | 8010 |
| Damudya | ডামুড্যা | 327 | 8040 |
| Naria | নড়িয়া | 323 | 8020 |
| Bhedarganj | ভেদরগঞ্জ | 326 | 8030 |
| Shariatpur Sadar | শরিয়তপুর সদর | 322 | 8000 |

## Division: Rangpur (ID 7) রংপুর

### Kurigram (ID 60) কুড়িগ্রাম

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Ulipur | উলিপুর | 453 | 5620 |
| Kurigram Sadar | কুড়িগ্রাম সদর | 448 | 5600 |
| Charrajibpur | চর রাজিবপুর | 456 | 5650 |
| Chilmari | চিলমারী | 454 | 5630 |
| Nageshwari | নাগেশ্বরী | 449 | 5660 |
| Phulbari | ফুলবাড়ী | 451 | 5680 |
| Bhurungamari | ভুরুঙ্গামারী | 450 | 5670 |
| Rajarhat | রাজারহাট | 452 | 5610 |
| Rowmari | রৌমারী | 455 | 5640 |

### Gaibandha (ID 57) গাইবান্ধা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Gaibandha Sadar | গাইবান্ধা সদর | 429 | 5700 |
| Gobindaganj | গোবিন্দগঞ্জ | 432 | 5740 |
| Palashbari | পলাশবাড়ী | 430 | 5730 |
| Phulchari | ফুলছড়ি | 434 | 5760 |
| Saghata | সাঘাটা | 431 | 5751 |
| Sadullapur | সাদুল্লাপুর | 428 | 5710 |
| Sundarganj | সুন্দরগঞ্জ | 433 | 5720 |

### Thakurgaon (ID 58) ঠাকুরগাঁও

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Thakurgaon Sadar | ঠাকুরগাঁও সদর | 435 | 5100 |
| Pirganj | পীরগঞ্জ | 436 | 5110 |
| Baliadangi | বালিয়াডাঙ্গী | 439 | 5140 |
| Ranisankail | রাণীশংকৈল | 437 | 5120 |
| Haripur | হরিপুর | 438 | 1741 |

### Dinajpur (ID 54) দিনাজপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kaharol | কাহারোল | 410 | 5226 |
| Khansama | খানসামা | 414 | 5230 |
| Ghoraghat | ঘোড়াঘাট | 406 | 5291 |
| Chirirbandar | চিরিরবন্দর | 416 | 5240 |
| Dinajpur Sadar | দিনাজপুর সদর | 412 | 5200 |
| Nawabganj | নবাবগঞ্জ | 404 | 5280 |
| Parbatipur | পার্বতীপুর | 408 | 5250 |
| Fulbari | ফুলবাড়ী | 411 | 5260 |
| Birol | বিরল | 415 | 5210 |
| Birampur | বিরামপুর | 407 | 5266 |
| Birganj | বীরগঞ্জ | 405 | 5220 |
| Bochaganj | বোচাগঞ্জ | 409 | 5216 |
| Hakimpur | হাকিমপুর | 413 | 5270 |

### Nilphamari (ID 56) নীলফামারী

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kishorganj | কিশোরগঞ্জ | 426 | 5320 |
| Jaldhaka | জলঢাকা | 425 | 5330 |
| Dimla | ডিমলা | 424 | 5350 |
| Domar | ডোমার | 423 | 5340 |
| Nilphamari Sadar | নীলফামারী সদর | 427 | 5300 |
| Syedpur | সৈয়দপুর | 422 | 5310 |

### Panchagarh (ID 53) পঞ্চগড়

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Atwari | আটোয়ারী | 402 | 5041 |
| Tetulia | তেতুলিয়া | 403 | 5030 |
| Debiganj | দেবীগঞ্জ | 400 | 5020 |
| Panchagarh Sadar | পঞ্চগড় সদর | 399 | 5000 |
| Boda | বোদা | 401 | 5010 |

### Rangpur (ID 59) রংপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Kaunia | কাউনিয়া | 446 | 5440 |
| Gangachara | গংগাচড়া | 441 | 5410 |
| Taragonj | তারাগঞ্জ | 442 | 5420 |
| Pirgonj | পীরগঞ্জ | 445 | 5110 |
| Pirgacha | পীরগাছা | 447 | 5450 |
| Badargonj | বদরগঞ্জ | 443 | 5430 |
| Mithapukur | মিঠাপুকুর | 444 | 5460 |
| Rangpur Sadar | রংপুর সদর | 440 | 5400 |

### Lalmonirhat (ID 55) লালমনিরহাট

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Aditmari | আদিতমারী | 421 | 5510 |
| Kaliganj | কালীগঞ্জ | 418 | 1720 |
| Patgram | পাটগ্রাম | 420 | 5540 |
| Lalmonirhat Sadar | লালমনিরহাট সদর | 417 | 5500 |
| Hatibandha | হাতীবান্ধা | 419 | 5530 |

## Division: Mymensingh (ID 8) ময়মনসিংহ

### Jamalpur (ID 63) জামালপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Islampur | ইসলামপুর | 477 | 2020 |
| Jamalpur Sadar | জামালপুর সদর | 475 | 2000 |
| Dewangonj | দেওয়ানগঞ্জ | 478 | 2030 |
| Bokshiganj | বকশীগঞ্জ | 481 | 2140 |
| Madarganj | মাদারগঞ্জ | 480 | 5430 |
| Melandah | মেলান্দহ | 476 | 2010 |
| Sarishabari | সরিষাবাড়ী | 479 | 2050 |

### Netrokona (ID 64) নেত্রকোণা

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Atpara | আটপাড়া | 485 | 2470 |
| Kalmakanda | কলমাকান্দা | 488 | 2430 |
| Kendua | কেন্দুয়া | 484 | 2480 |
| Khaliajuri | খালিয়াজুরী | 487 | 2460 |
| Durgapur | দুর্গাপুর | 483 | 6240 |
| Netrokona Sadar | নেত্রকোণা সদর | 491 | 2400 |
| Purbadhala | পূর্বধলা | 490 | 2410 |
| Barhatta | বারহাট্টা | 482 | 2440 |
| Madan | মদন | 486 | 2490 |
| Mohongonj | মোহনগঞ্জ | 489 | 2446 |

### Mymensingh (ID 62) ময়মনসিংহ

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Iswarganj | ঈশ্বরগঞ্জ | 472 | 2282 |
| Gafargaon | গফরগাঁও | 471 | 2230 |
| Gouripur | গৌরীপুর | 470 | 2270 |
| Tarakanda | তারাকান্দা | 474 | 2252 |
| Trishal | ত্রিশাল | 463 | 2220 |
| Dhobaura | ধোবাউড়া | 467 | 2416 |
| Nandail | নান্দাইল | 473 | 2290 |
| Phulpur | ফুলপুর | 468 | 2250 |
| Fulbaria | ফুলবাড়ীয়া | 462 | 2216 |
| Bhaluka | ভালুকা | 464 | 2240 |
| Muktagacha | মুক্তাগাছা | 465 | 2210 |
| Mymensingh Sadar | ময়মনসিংহ সদর | 466 | 2200 |
| Haluaghat | হালুয়াঘাট | 469 | 2260 |

### Sherpur (ID 61) শেরপুর

| Upazila (EN) | Upazila (BN) | ID | Zip Code |
|--------------|--------------|----|----------|
| Jhenaigati | ঝিনাইগাতী | 461 | 2120 |
| Nokla | নকলা | 460 | 2150 |
| Nalitabari | নালিতাবাড়ী | 458 | 2110 |
| Sherpur Sadar | শেরপুর সদর | 457 | 2100 |
| Sreebordi | শ্রীবরদী | 459 | 2130 |

