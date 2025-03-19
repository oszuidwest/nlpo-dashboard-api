# NLPO Dashboard endpoint voor WordPress

Deze WordPress plugin implementeert een REST API endpoint voor het aanleveren van artikeldata aan het [NLPO Bereiksdashboard](https://www.nlpo.nl/dashboard/). Omdat het dashboard standaard geen Plausible analytics ondersteunt, heeft Streekomroep ZuidWest een alternatieve implementatie geschreven. 

## Over deze plugin

De plugin voegt een REST API endpoint toe aan WordPress die artikelgegevens volgens de specificaties van het NLPO Dashboard beschikbaar stelt. De plugin haalt alle gepubliceerde artikelen van het type `post` en de paginaweergaven van die artikelen uit Plausible Analytics. Vervolgens combineert deze het geheel tot één gestandaardiseerde output. Deze data wordt in het NLPO Dashboard gebruikt voor het rapporteren van het bereik van lokale/streekomroepen.

## Installatie

1. Download de pluginbestanden en plaats deze in `/wp-content/plugins/nlpo-api/`
2. Activeer de plugin via WordPress 
3. Configureer de vereiste instellingen in het plugin-bestand:
   ```php
   define('NLPO_PLAUSIBLE_BASE_URL', 'jouw-plausible-url/api');
   define('NLPO_PLAUSIBLE_SITE_ID', 'jouw-site-id');
   define('NLPO_PLAUSIBLE_TOKEN', 'jouw-plausible-token');
   define('NLPO_API_TOKEN', 'jouw-beveiligde-api-token');
   ```

## Configuratie

Voor de werking van de plugin heb je de volgende gegevens nodig:

- `NLPO_PLAUSIBLE_BASE_URL`: De URL van je Plausible Analytics installatie
- `NLPO_PLAUSIBLE_SITE_ID`: Het website ID in Plausible Analytics
- `NLPO_PLAUSIBLE_TOKEN`: Een Plausible Analytics API token met leestoegang
- `NLPO_API_TOKEN`: Een zelf gekozen beveiligingstoken voor de API toegang
- `NLPO_CACHE_EXPIRATION`: Hoe lang statistieken gecached worden in seconden (standaard: 3600)

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
        "tags": ["Tag1", "Tag2"],
        "comment_count": 0,
        "views": 1234
    }
]
```

Deze implementatie-details zijn gebruikt: https://www.nlpo.nl/wp-content/uploads/2024/07/240711-PRD-_-Custom_API-v2-.pdf

## Technische informatie

Het endpoint is beschikbaar op:
```
GET /wp-json/zw/v1/nlpo?token=jouw-api-token&from=2024-01-01&to=2024-01-31
```

Parameters:
- `token` (verplicht): Je API token
- `from` (optioneel): Startdatum (YYYY-MM-DD)
- `to` (optioneel): Einddatum (YYYY-MM-DD)

Zonder datums worden artikelen van de laatste 7 dagen teruggegeven. Standaard wordt data uit Plausible Analytics een uur gecached om hammering van de service te voorkomen.

## Tags en regio's

De plugin controleert of posts een 'regio' taxonomie hebben:
- Als een post de 'regio' taxonomie heeft, worden deze termen gebruikt voor de 'tags' veld
- Als een post geen 'regio' taxonomie heeft, worden de standaard WordPress tags gebruikt
- Als beide niet beschikbaar zijn, wordt een lege array teruggegeven

## Licentie

Deze plugin is gelicenseerd onder de MIT licentie.

```
MIT License

Copyright (c) 2025 Streekomroep ZuidWest

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
