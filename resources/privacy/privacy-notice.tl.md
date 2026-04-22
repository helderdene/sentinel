# Paunawa sa Privacy

Ang CDRRMO ng Lungsod ng Butuan ay nagpapatakbo ng Incident Response
Management System (IRMS), na kinabibilangan ng Face Recognition Alert
System (FRAS). Ipinapaliwanag ng paunawang ito kung anong personal at
biometric na impormasyon ang aming kinokolekta sa pamamagitan ng FRAS,
kung bakit namin ito kinokolekta, gaano katagal namin itinatago, paano
namin pinoprotektahan, at ang inyong mga karapatan sa ilalim ng
Republic Act 10173 (ang Data Privacy Act) at ang mga implementing rules
nito.

Huling na-update: 2026-04-22.

## Anong impormasyon ang aming kinokolekta

### Mga larawan ng mukha mula sa CCTV cameras

Ang mga FRAS camera na naka-install sa mga itinalagang pampublikong
lugar ng Butuan City ay kumukuha ng mga larawan ng mga mukha na dumadaan
sa field of view. Ang mga larawang ito ay pinoproseso upang matukoy ang
mga recognition match laban sa isang kontroladong watchlist ng mga
persons of interest (mga block-list subject, nawawalang tao, at lost-child
na alerto na inirehistro ng mga awtorisadong tauhan).

### Mga recognition events

Ang bawat recognition match ay gumagawa ng record na naglalaman ng
larawan ng mukha, scene image (ang mas malawak na frame kung saan
natagpuan ang mukha), ang camera identifier at lokasyon, ang oras ng
pag-capture, ang match confidence score, at ang kategorya ng subject
(block, nawawala, o lost child).

### Mga access logs

Sa bawat pagkakataon na ang isang CDRRMO operator, supervisor, o
administrator ay tumitingin ng larawan ng mukha o scene image na
naka-imbak sa FRAS, ang sistema ay nag-rerekord ng access log entry na
naglalaman ng pagkakakilanlan ng tumitingin, ang kanilang IP address,
user-agent, at oras ng pag-access. Ang mga log na ito ay append-only at
tamper-evident.

## Bakit namin kinokolekta (legal na batayan)

Pinoproseso ng CDRRMO ang impormasyong ito bilang bahagi ng tungkuling
pampubliko — partikular, emergency response at public safety operations
sa ilalim ng Philippine Disaster Risk Reduction and Management Act
(Republic Act 10121) at ng mandato ng lungsod na tuklasin at tumugon sa
mga insidenteng makakaapekto sa mga persons of interest. Nakasalalay kami
sa Section 12(e) at Section 13(f) ng RA 10173 bilang aming legal na
batayan para sa pagproseso ng personal at sensitibong personal na
impormasyon.

## Gaano katagal namin itinatago (retention)

Ang mga face crop at scene image ay itinatago lamang hangga't kinakailangan:

- **Scene images:** 30 araw mula sa pag-capture.
- **Face crops:** 90 araw mula sa pag-capture.
- **Eksepsiyon:** kung ang isang larawan ay nakatali sa isang aktibo o
  nalutas na insidente, ito ay itinatago hanggang sa ma-resolve o
  ma-cancel ang insidente at sa karagdagang panahon para sa after-action
  review.
- **Access logs:** 2 taon, ayon sa compliance audit requirement.

Pagkatapos ng retention period, ang mga larawan ay tatanggalin sa primary
storage ng isang automated purge job na tumatakbo araw-araw.

## Ang inyong mga karapatan bilang data subject

Sa ilalim ng RA 10173, mayroon kayong mga sumusunod na karapatan kaugnay
ng inyong personal na impormasyon:

1. **Karapatan na maipaalam** — ipinapaalam namin sa inyo sa pamamagitan
   ng paunawang ito.
2. **Karapatan sa pag-access** — maaari kayong humingi ng kopya ng
   personal na impormasyon na aming hawak.
3. **Karapatan na tumutol** — maaari kayong tumutol sa pagproseso ng
   inyong personal na impormasyon, alinsunod sa legal na batayan.
4. **Karapatan sa pagbura o pag-block** — maaari kayong humiling na
   burahin o i-block ang personal na impormasyon na hindi na kailangan,
   hindi kumpleto, luma na, o ilegal na nakuha.
5. **Karapatan sa pagwawasto** — maaari kayong humiling ng pagwawasto ng
   hindi tumpak o hindi kumpletong impormasyon.
6. **Karapatan sa data portability** — kung naaangkop, maaari ninyong
   makuha at magamit muli ang inyong personal na impormasyon sa
   structured, commonly-used electronic format.
7. **Karapatan sa damages** — maaari kayong ma-indemnify para sa mga
   damages dahil sa hindi tumpak, hindi kumpleto, luma, mali,
   ilegal-na-nakuha, o hindi awtorisadong paggamit ng personal na
   impormasyon.
8. **Karapatan na mag-file ng reklamo** — maaari kayong mag-file ng
   reklamo sa National Privacy Commission (NPC).

Upang magamit ang alinman sa mga karapatang ito, makipag-ugnayan sa
aming Data Protection Officer gamit ang mga detalye sa ibaba.

## Sino ang may access sa inyong data

Ang access sa FRAS imagery at recognition data ay may role-restriction:

- **Operators** — maaaring tumingin ng recognition events, scene images,
  at face images sa kurso ng pagre-review ng mga alerto at paglikha ng
  mga insidente.
- **Dispatchers** — maaaring tumingin ng recognition events PERO HINDI
  ang mga face o scene images.
- **Supervisors at Administrators** — maaaring tumingin ng lahat ng
  nabanggit, kasama ang audit logs ng image-access events.
- **Responders** (mga field unit) — nakikita lamang ang pangalan ng
  personnel, kategorya, lokasyon ng camera, at face thumbnail para sa
  operational context. HINDI nila nakikita ang mas malawak na scene image,
  at ang face thumbnail ay kontrolado ng parehong role controls.

## Paano namin pinoprotektahan ang inyong data

- Ang lahat ng FRAS image URL ay may time-limit (5 minuto) at
  cryptographically-signed — hindi ito maaaring i-share, i-bookmark, o
  gamitin muli.
- Bawat image-view request ay gumagawa ng append-only audit log entry na
  sinusulat sa loob ng parehong database transaction tulad ng stream —
  ang pagkabigo ng database ay humihinto sa stream, kaya't hinding-hindi
  kami maghahatid ng larawan nang walang log.
- Ang image storage ay gumagamit ng private disk; walang public web path
  na direktang naglalantad ng FRAS imagery.
- Ang access ay kontrolado ng role-based authorisation na ipinapatupad sa
  tatlong layer (HTTP controller, broadcast channel, at page prop).
- Ang production data ay naka-encrypt sa pahinga. Ang transport ay
  naka-encrypt sa TLS.

## Makipag-ugnayan sa aming Data Protection Officer

Para sa anumang katanungan, kahilingan, o reklamo kaugnay ng paunawang
ito o ng personal na impormasyon na aming hawak, makipag-ugnayan sa:

- **Pangalan:** [CDRRMO_DPO_NAME]
- **Email:** [CDRRMO_DPO_EMAIL]
- **Telepono:** [CDRRMO_DPO_PHONE]
- **Address ng Tanggapan:** [CDRRMO_DPO_OFFICE_ADDRESS]

## Pagsampa ng reklamo sa NPC

Kung naniniwala kayong nilabag ang inyong mga karapatan sa ilalim ng
RA 10173, maaari kayong mag-file ng reklamo sa National Privacy
Commission:

- **Website:** https://privacy.gov.ph
- **Email:** info@privacy.gov.ph
- **Address:** 5th Floor, Philippine International Convention Center
  (PICC), Pasay City, Metro Manila, Philippines
