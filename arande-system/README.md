# Ärendehanteringssystem – Grundstruktur

## Funktioner
- Inloggning med e-post och lösenord
- Valbar tvåfaktorsautentisering (6-siffrig kod via e-post)
- Admin: skapa företag, koppla användare, hantera ytor
- Portal och admin med vänstermeny (hamburgermeny) och toppbanner
- Språkstöd via språkfil (alla texter, menyer, rubriker)
- Databas-prefix: `ahs_`

## Struktur
- `/arande-system/public/` – Publika sidor (inloggning, portal)
- `/arande-system/admin/` – Adminpanel
- `/arande-system/lang/` – Språkfiler
- `/arande-system/inc/` – Gemensam kod (t.ex. databas, auth, helpers)
- `/arande-system/assets/` – CSS, JS, bilder

## Start
1. Lägg till databas enligt `db.sql`
2. Kopiera och anpassa `lang/sv.php` för fler språk
3. Starta med `public/index.php` (inloggning)
