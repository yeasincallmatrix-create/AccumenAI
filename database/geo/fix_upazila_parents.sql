-- Fix Upazila Parent IDs
-- Generated: 2026-09-05 14:03:28
-- Total upazilas to update: 596
--
-- This script fixes scrambled parent_id values for upazilas (level 3).
-- Each upazila's parent_id is set to the correct district (level 2) ID.
-- The JSON parent_code values were systematically scrambled during import.
--
-- Format: UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = '{district_code}') WHERE code = '{upazila_code}';
--

-- District: Comilla (BD.T1) — 17 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U1';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U2';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U3';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U4';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U5';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U6';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U7';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U8';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U9';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U10';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U11';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U12';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U13';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U14';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U15';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U16';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T1') WHERE code = 'BD.U17';

-- District: Feni (BD.T2) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T2') WHERE code = 'BD.U18';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T2') WHERE code = 'BD.U19';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T2') WHERE code = 'BD.U20';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T2') WHERE code = 'BD.U21';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T2') WHERE code = 'BD.U22';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T2') WHERE code = 'BD.U23';

-- District: Chandpur (BD.T3) — 8 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T3') WHERE code = 'BD.U52';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T3') WHERE code = 'BD.U53';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T3') WHERE code = 'BD.U54';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T3') WHERE code = 'BD.U55';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T3') WHERE code = 'BD.U56';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T3') WHERE code = 'BD.U57';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T3') WHERE code = 'BD.U58';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T3') WHERE code = 'BD.U59';

-- District: Lakshmipur (BD.T4) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T4') WHERE code = 'BD.U60';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T4') WHERE code = 'BD.U61';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T4') WHERE code = 'BD.U62';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T4') WHERE code = 'BD.U63';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T4') WHERE code = 'BD.U64';

-- District: Noakhali (BD.T5) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U43';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U44';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U45';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U46';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U47';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U48';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U49';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U50';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T5') WHERE code = 'BD.U51';

-- District: Chattogram (BD.T6) — 29 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U65';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U66';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U67';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U68';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U69';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U70';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U71';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U72';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U73';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U74';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U75';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U76';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U77';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U78';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U79';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U540';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U541';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U542';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U543';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U544';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U545';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U546';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U547';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U548';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U549';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U550';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U551';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U552';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T6') WHERE code = 'BD.U553';

-- District: Cox's Bazar (BD.T7) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U80';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U81';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U82';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U83';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U84';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U85';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U86';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U87';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T7') WHERE code = 'BD.U492';

-- District: Khagrachhari (BD.T8) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U88';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U89';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U90';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U91';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U92';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U93';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U94';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U95';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T8') WHERE code = 'BD.U96';

-- District: Bandarban (BD.T9) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T9') WHERE code = 'BD.U97';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T9') WHERE code = 'BD.U98';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T9') WHERE code = 'BD.U99';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T9') WHERE code = 'BD.U100';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T9') WHERE code = 'BD.U101';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T9') WHERE code = 'BD.U102';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T9') WHERE code = 'BD.U103';

-- District: Rangamati (BD.T10) — 10 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U33';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U34';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U35';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U36';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U37';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U38';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U39';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U40';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U41';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T10') WHERE code = 'BD.U42';

-- District: Mymensingh (BD.T11) — 13 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U462';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U463';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U464';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U465';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U466';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U467';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U468';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U469';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U470';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U471';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U472';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U473';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T11') WHERE code = 'BD.U474';

-- District: Sherpur (BD.T12) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T12') WHERE code = 'BD.U457';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T12') WHERE code = 'BD.U458';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T12') WHERE code = 'BD.U459';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T12') WHERE code = 'BD.U460';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T12') WHERE code = 'BD.U461';

-- District: Jamalpur (BD.T13) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T13') WHERE code = 'BD.U475';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T13') WHERE code = 'BD.U476';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T13') WHERE code = 'BD.U477';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T13') WHERE code = 'BD.U478';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T13') WHERE code = 'BD.U479';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T13') WHERE code = 'BD.U480';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T13') WHERE code = 'BD.U481';

-- District: Netrokona (BD.T14) — 10 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U482';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U483';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U484';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U485';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U486';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U487';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U488';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U489';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U490';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T14') WHERE code = 'BD.U491';

-- District: Sunamganj (BD.T15) — 12 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U300';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U301';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U302';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U303';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U304';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U305';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U306';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U307';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U308';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U309';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U310';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T15') WHERE code = 'BD.U493';

-- District: Sylhet (BD.T16) — 20 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U272';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U273';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U274';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U275';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U276';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U277';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U278';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U279';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U280';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U281';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U282';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U283';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U284';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U577';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U578';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U579';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U580';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U581';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U582';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T16') WHERE code = 'BD.U583';

-- District: Habiganj (BD.T17) — 8 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T17') WHERE code = 'BD.U292';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T17') WHERE code = 'BD.U293';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T17') WHERE code = 'BD.U294';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T17') WHERE code = 'BD.U295';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T17') WHERE code = 'BD.U296';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T17') WHERE code = 'BD.U297';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T17') WHERE code = 'BD.U298';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T17') WHERE code = 'BD.U299';

-- District: Moulvibazar (BD.T18) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T18') WHERE code = 'BD.U285';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T18') WHERE code = 'BD.U286';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T18') WHERE code = 'BD.U287';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T18') WHERE code = 'BD.U288';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T18') WHERE code = 'BD.U289';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T18') WHERE code = 'BD.U290';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T18') WHERE code = 'BD.U291';

-- District: Dhaka (BD.T19) — 50 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U365';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U366';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U367';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U368';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U369';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U495';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U496';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U497';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U498';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U499';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U500';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U501';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U502';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U503';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U504';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U505';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U506';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U507';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U508';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U509';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U510';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U511';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U512';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U513';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U514';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U515';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U516';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U517';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U518';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U519';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U520';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U521';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U522';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U523';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U524';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U525';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U526';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U527';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U528';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U529';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U530';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U531';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U532';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U533';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U534';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U535';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U536';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U537';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U538';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T19') WHERE code = 'BD.U539';

-- District: Faridpur (BD.T20) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U390';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U391';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U392';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U393';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U394';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U395';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U396';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U397';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T20') WHERE code = 'BD.U398';

-- District: Gopalganj (BD.T21) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T21') WHERE code = 'BD.U385';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T21') WHERE code = 'BD.U386';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T21') WHERE code = 'BD.U387';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T21') WHERE code = 'BD.U388';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T21') WHERE code = 'BD.U389';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T21') WHERE code = 'BD.U494';

-- District: Madaripur (BD.T22) — 4 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T22') WHERE code = 'BD.U381';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T22') WHERE code = 'BD.U382';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T22') WHERE code = 'BD.U383';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T22') WHERE code = 'BD.U384';

-- District: Rajbari (BD.T23) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T23') WHERE code = 'BD.U376';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T23') WHERE code = 'BD.U377';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T23') WHERE code = 'BD.U378';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T23') WHERE code = 'BD.U379';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T23') WHERE code = 'BD.U380';

-- District: Shariatpur (BD.T24) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T24') WHERE code = 'BD.U322';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T24') WHERE code = 'BD.U323';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T24') WHERE code = 'BD.U324';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T24') WHERE code = 'BD.U325';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T24') WHERE code = 'BD.U326';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T24') WHERE code = 'BD.U327';

-- District: Tangail (BD.T25) — 12 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U333';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U334';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U335';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U336';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U337';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U338';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U339';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U340';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U341';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U342';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U343';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T25') WHERE code = 'BD.U344';

-- District: Kishoreganj (BD.T26) — 13 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U345';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U346';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U347';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U348';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U349';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U350';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U351';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U352';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U353';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U354';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U355';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U356';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T26') WHERE code = 'BD.U357';

-- District: Manikganj (BD.T27) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T27') WHERE code = 'BD.U358';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T27') WHERE code = 'BD.U359';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T27') WHERE code = 'BD.U360';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T27') WHERE code = 'BD.U361';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T27') WHERE code = 'BD.U362';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T27') WHERE code = 'BD.U363';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T27') WHERE code = 'BD.U364';

-- District: Munshiganj (BD.T28) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T28') WHERE code = 'BD.U370';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T28') WHERE code = 'BD.U371';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T28') WHERE code = 'BD.U372';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T28') WHERE code = 'BD.U373';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T28') WHERE code = 'BD.U374';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T28') WHERE code = 'BD.U375';

-- District: Narayanganj (BD.T29) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T29') WHERE code = 'BD.U328';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T29') WHERE code = 'BD.U329';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T29') WHERE code = 'BD.U330';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T29') WHERE code = 'BD.U331';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T29') WHERE code = 'BD.U332';

-- District: Narsingdi (BD.T30) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T30') WHERE code = 'BD.U311';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T30') WHERE code = 'BD.U312';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T30') WHERE code = 'BD.U313';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T30') WHERE code = 'BD.U314';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T30') WHERE code = 'BD.U315';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T30') WHERE code = 'BD.U316';

-- District: Gazipur (BD.T31) — 12 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U317';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U318';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U319';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U320';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U321';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U584';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U585';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U586';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U587';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U588';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U589';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T31') WHERE code = 'BD.U590';

-- District: Brahmanbaria (BD.T32) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U24';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U25';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U26';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U27';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U28';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U29';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U30';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U31';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T32') WHERE code = 'BD.U32';

-- District: Barguna (BD.T33) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T33') WHERE code = 'BD.U266';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T33') WHERE code = 'BD.U267';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T33') WHERE code = 'BD.U268';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T33') WHERE code = 'BD.U269';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T33') WHERE code = 'BD.U270';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T33') WHERE code = 'BD.U271';

-- District: Barishal (BD.T34) — 14 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U249';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U250';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U251';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U252';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U253';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U254';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U255';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U256';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U257';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U258';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U573';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U574';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U575';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T34') WHERE code = 'BD.U576';

-- District: Bhola (BD.T35) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T35') WHERE code = 'BD.U259';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T35') WHERE code = 'BD.U260';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T35') WHERE code = 'BD.U261';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T35') WHERE code = 'BD.U262';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T35') WHERE code = 'BD.U263';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T35') WHERE code = 'BD.U264';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T35') WHERE code = 'BD.U265';

-- District: Jhalakathi (BD.T36) — 4 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T36') WHERE code = 'BD.U230';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T36') WHERE code = 'BD.U231';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T36') WHERE code = 'BD.U232';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T36') WHERE code = 'BD.U233';

-- District: Patuakhali (BD.T37) — 8 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T37') WHERE code = 'BD.U234';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T37') WHERE code = 'BD.U235';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T37') WHERE code = 'BD.U236';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T37') WHERE code = 'BD.U237';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T37') WHERE code = 'BD.U238';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T37') WHERE code = 'BD.U239';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T37') WHERE code = 'BD.U240';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T37') WHERE code = 'BD.U241';

-- District: Pirojpur (BD.T38) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T38') WHERE code = 'BD.U242';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T38') WHERE code = 'BD.U243';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T38') WHERE code = 'BD.U244';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T38') WHERE code = 'BD.U245';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T38') WHERE code = 'BD.U246';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T38') WHERE code = 'BD.U247';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T38') WHERE code = 'BD.U248';

-- District: Bogura (BD.T39) — 12 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U122';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U123';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U124';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U125';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U126';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U127';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U128';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U129';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U130';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U131';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U132';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T39') WHERE code = 'BD.U133';

-- District: Joypurhat (BD.T40) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T40') WHERE code = 'BD.U150';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T40') WHERE code = 'BD.U151';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T40') WHERE code = 'BD.U152';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T40') WHERE code = 'BD.U153';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T40') WHERE code = 'BD.U154';

-- District: Naogaon (BD.T41) — 11 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U160';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U161';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U162';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U163';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U164';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U165';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U166';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U167';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U168';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U169';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T41') WHERE code = 'BD.U170';

-- District: Natore (BD.T42) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T42') WHERE code = 'BD.U143';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T42') WHERE code = 'BD.U144';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T42') WHERE code = 'BD.U145';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T42') WHERE code = 'BD.U146';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T42') WHERE code = 'BD.U147';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T42') WHERE code = 'BD.U148';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T42') WHERE code = 'BD.U149';

-- District: Chapainawabganj (BD.T43) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T43') WHERE code = 'BD.U155';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T43') WHERE code = 'BD.U156';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T43') WHERE code = 'BD.U157';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T43') WHERE code = 'BD.U158';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T43') WHERE code = 'BD.U159';

-- District: Pabna (BD.T44) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U113';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U114';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U115';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U116';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U117';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U118';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U119';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U120';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T44') WHERE code = 'BD.U121';

-- District: Sirajganj (BD.T45) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U104';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U105';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U106';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U107';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U108';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U109';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U110';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U111';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T45') WHERE code = 'BD.U112';

-- District: Dinajpur (BD.T46) — 13 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U404';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U405';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U406';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U407';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U408';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U409';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U410';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U411';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U412';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U413';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U414';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U415';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T46') WHERE code = 'BD.U416';

-- District: Gaibandha (BD.T47) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T47') WHERE code = 'BD.U428';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T47') WHERE code = 'BD.U429';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T47') WHERE code = 'BD.U430';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T47') WHERE code = 'BD.U431';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T47') WHERE code = 'BD.U432';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T47') WHERE code = 'BD.U433';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T47') WHERE code = 'BD.U434';

-- District: Kurigram (BD.T48) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U448';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U449';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U450';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U451';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U452';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U453';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U454';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U455';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T48') WHERE code = 'BD.U456';

-- District: Lalmonirhat (BD.T49) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T49') WHERE code = 'BD.U417';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T49') WHERE code = 'BD.U418';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T49') WHERE code = 'BD.U419';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T49') WHERE code = 'BD.U420';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T49') WHERE code = 'BD.U421';

-- District: Nilphamari (BD.T50) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T50') WHERE code = 'BD.U422';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T50') WHERE code = 'BD.U423';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T50') WHERE code = 'BD.U424';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T50') WHERE code = 'BD.U425';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T50') WHERE code = 'BD.U426';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T50') WHERE code = 'BD.U427';

-- District: Panchagarh (BD.T51) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T51') WHERE code = 'BD.U399';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T51') WHERE code = 'BD.U400';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T51') WHERE code = 'BD.U401';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T51') WHERE code = 'BD.U402';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T51') WHERE code = 'BD.U403';

-- District: Rangpur (BD.T52) — 14 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U440';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U441';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U442';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U443';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U444';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U445';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U446';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U447';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U591';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U592';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U593';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U594';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U595';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T52') WHERE code = 'BD.U596';

-- District: Thakurgaon (BD.T53) — 5 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T53') WHERE code = 'BD.U435';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T53') WHERE code = 'BD.U436';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T53') WHERE code = 'BD.U437';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T53') WHERE code = 'BD.U438';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T53') WHERE code = 'BD.U439';

-- District: Rajshahi (BD.T54) — 20 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U134';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U135';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U136';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U137';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U138';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U139';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U140';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U141';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U142';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U554';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U555';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U556';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U557';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U558';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U559';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U560';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U561';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U562';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U563';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T54') WHERE code = 'BD.U564';

-- District: Bagerhat (BD.T55) — 9 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U215';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U216';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U217';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U218';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U219';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U220';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U221';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U222';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T55') WHERE code = 'BD.U223';

-- District: Chuadanga (BD.T56) — 4 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T56') WHERE code = 'BD.U192';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T56') WHERE code = 'BD.U193';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T56') WHERE code = 'BD.U194';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T56') WHERE code = 'BD.U195';

-- District: Jessore (BD.T57) — 8 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T57') WHERE code = 'BD.U171';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T57') WHERE code = 'BD.U172';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T57') WHERE code = 'BD.U173';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T57') WHERE code = 'BD.U174';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T57') WHERE code = 'BD.U175';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T57') WHERE code = 'BD.U176';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T57') WHERE code = 'BD.U177';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T57') WHERE code = 'BD.U178';

-- District: Jhenaidah (BD.T58) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T58') WHERE code = 'BD.U224';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T58') WHERE code = 'BD.U225';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T58') WHERE code = 'BD.U226';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T58') WHERE code = 'BD.U227';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T58') WHERE code = 'BD.U228';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T58') WHERE code = 'BD.U229';

-- District: Khulna (BD.T59) — 17 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U206';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U207';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U208';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U209';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U210';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U211';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U212';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U213';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U214';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U565';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U566';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U567';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U568';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U569';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U570';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U571';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T59') WHERE code = 'BD.U572';

-- District: Kushtia (BD.T60) — 6 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T60') WHERE code = 'BD.U196';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T60') WHERE code = 'BD.U197';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T60') WHERE code = 'BD.U198';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T60') WHERE code = 'BD.U199';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T60') WHERE code = 'BD.U200';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T60') WHERE code = 'BD.U201';

-- District: Magura (BD.T61) — 4 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T61') WHERE code = 'BD.U202';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T61') WHERE code = 'BD.U203';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T61') WHERE code = 'BD.U204';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T61') WHERE code = 'BD.U205';

-- District: Meherpur (BD.T62) — 3 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T62') WHERE code = 'BD.U186';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T62') WHERE code = 'BD.U187';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T62') WHERE code = 'BD.U188';

-- District: Narail (BD.T63) — 3 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T63') WHERE code = 'BD.U189';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T63') WHERE code = 'BD.U190';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T63') WHERE code = 'BD.U191';

-- District: Satkhira (BD.T64) — 7 upazilas
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T64') WHERE code = 'BD.U179';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T64') WHERE code = 'BD.U180';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T64') WHERE code = 'BD.U181';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T64') WHERE code = 'BD.U182';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T64') WHERE code = 'BD.U183';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T64') WHERE code = 'BD.U184';
UPDATE administrative_units SET parent_id = (SELECT id FROM administrative_units WHERE code = 'BD.T64') WHERE code = 'BD.U185';

