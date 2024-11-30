# NLPO Dashboard WordPress Plugin

Deze WordPress plugin implementeert een beveiligde REST API endpoint voor het aanleveren van artikeldata aan het NLPO dashboard. Omdat het dashboard standaard geen Plausible analytics ondersteunt, heeft Streekomroep ZuidWest zijn eigen implementatie geschreven.

## Over deze plugin

De plugin voegt een REST API endpoint toe aan je WordPress website die artikelgegevens volgens de NLPO specificaties beschikbaar stelt. De plugin haalt naast de standaard WordPress gegevens ook de paginaweergaven uit Plausible Analytics op en combineert deze tot één gestandaardiseerde output. Deze data wordt door het NLPO dashboard gebruikt om statistieken en inzichten te genereren over het bereik van lokale/streekomroepen.

## Installatie

1. Download de plugin bestanden en plaats deze in je `/wp-content/plugins/nlpo-api/` map
2. Activeer de plugin via de WordPress beheeromgeving
3. Configureer de vereiste instellingen in het plugin bestand:
   ```php
   define('NLPO_PLAUSIBLE_BASE_URL', 'jouw-plausible-url/api');
   define('NLPO_PLAUSIBLE_SITE_ID', 'jouw-site-id');
   define('NLPO_PLAUSIBLE_TOKEN', 'jouw-plausible-token');
   define('NLPO_API_TOKEN', 'jouw-beveiligde-api-token');
   ```

## Configuratie

Voor de werking van de plugin heb je de volgende gegevens nodig:

- `NLPO_PLAUSIBLE_BASE_URL`: De basis URL van je Plausible Analytics installatie
- `NLPO_PLAUSIBLE_SITE_ID`: Je website ID in Plausible Analytics
- `NLPO_PLAUSIBLE_TOKEN`: Een Plausible Analytics API token met leestoegang
- `NLPO_API_TOKEN`: Een zelf gekozen beveiligingstoken voor de API toegang
- `NLPO_CACHE_EXPIRATION`: Hoe lang statistieken gecacht worden in seconden (standaard: 3600)

## Data structuur

De plugin levert artikeldata aan in het door NLPO gespecificeerde formaat:

```json
[
    {
        "id": "123",
        "title": "Artikel Titel",
        "text": "Volledige artikel inhoud...",
        "url": "https://omroep.nl/artikel",
        "date": "2024-01-15T12:00:00+00:00",
        "author": "Redacteur Naam",
        "excerpt": "Artikel samenvatting...",
        "categories": ["Nieuws", "Lokaal"],
        "tags": ["Regio1", "Regio2"],
        "comment_count": 0,
        "views": 1234
    }
]
```

## Technische informatie

Het endpoint is beschikbaar op:
```
GET /wp-json/zw/v1/nlpo?token=jouw-api-token&from=2024-01-01&to=2024-01-31
```

Parameters:
- `token` (verplicht): Je API token
- `from` (optioneel): Startdatum (YYYY-MM-DD)
- `to` (optioneel): Einddatum (YYYY-MM-DD)

Zonder datums worden artikelen van de laatste 7 dagen teruggegeven.

## Licentie

Deze plugin is gelicenseerd onder de MIT licentie.

```
MIT License

Copyright (c) 2024 Streekomroep ZuidWest

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
