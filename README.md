# MijnBoulesClub

WordPress plugin voor het beheren van een Jeu de Boules vereniging.

> **Work in progress** – dit project is in actieve ontwikkeling en nog niet geschikt voor productiegebruik.

---

## Geplande modules

| Module | Status |
|---|---|
| Ledenadministratie | ✅ MVP gereed |
| Competitiebeheer | 🔜 Gepland |
| Toernooien | 🔜 Gepland |

---

## Ledenmodule

### Functionaliteit

- Leden aanmaken, bewerken en verwijderen via het WordPress admin dashboard
- Zoeken en sorteren in de ledenlijst
- Actief / inactief markeren per lid
- Validatie (naam verplicht, e-mail gecontroleerd op geldigheid)
- Alle acties beveiligd met nonces

### Admin-menu

**WordPress admin → Mijn Boules Club → Leden**

### Database

Tabel: `wp_mbc_members`

| Kolom | Type | Omschrijving |
|---|---|---|
| `id` | INT UNSIGNED | Primaire sleutel, auto-increment |
| `name` | VARCHAR(100) | Volledige naam van het lid |
| `email` | VARCHAR(150) | E-mailadres (optioneel) |
| `phone` | VARCHAR(30) | Telefoonnummer (optioneel) |
| `active` | TINYINT(1) | 1 = actief, 0 = inactief |
| `created_at` | DATETIME | Tijdstip van aanmaken |
| `updated_at` | DATETIME | Tijdstip van laatste wijziging |

De tabel wordt automatisch aangemaakt bij activatie van de plugin via `dbDelta`.

---

## Architectuur

```
mijnboulesclub/
├── mijnboulesclub.php          # Plugin bootstrap, constanten, activatie-hook
├── includes/
│   ├── core/
│   │   ├── class-mbc-loader.php      # Module bootstrapper
│   │   └── class-mbc-database.php    # Database laag (CRUD + schema)
│   └── modules/
│       └── members/
│           ├── class-mbc-members.php         # Business logic + validatie
│           └── class-mbc-members-admin.php   # Admin UI (WP_List_Table + formulieren)
├── admin/
├── public/
├── assets/
│   ├── css/
│   └── js/
└── languages/
```

### Verantwoordelijkheden per laag

- **Database** (`MBC_Database`) – alleen DB-operaties, nooit business-beslissingen
- **Business logic** (`MBC_Members`) – validatie, sanitering, roept Database aan
- **Admin UI** (`MBC_Members_Admin`) – rendering, routing, nonce-verificatie, roept Members aan

---

## Vereisten

- WordPress 6.0 of hoger
- PHP 8.1 of hoger
- MySQL 5.6 of hoger

## Installatie (development)

1. Clone deze repository
2. Kopieer de map `mijnboulesclub/` naar `/wp-content/plugins/`
3. Activeer de plugin via het WordPress Plugins scherm
4. Navigeer naar **Mijn Boules Club → Leden**

## Licentie

GPL v2 of later – zie [LICENSE](LICENSE) voor details.
