# NLPO Dashboard API-endpoint voor WordPress

Deze WordPress-plugin implementeert een REST API-endpoint voor het aanleveren van artikelgegevens aan het [NLPO Bereiksdashboard](https://www.nlpo.nl/dashboard/). Omdat het dashboard standaard geen Plausible Analytics ondersteunt, heeft Streekomroep ZuidWest een eigen implementatie ontwikkeld.

## Over deze plugin

De plugin voegt een REST API-endpoint toe aan WordPress dat artikelgegevens beschikbaar stelt volgens NLPO-specificaties. Het combineert gepubliceerde artikelen met paginaweergaven uit Plausible Analytics voor rapportages over het bereik van lokale- en streekomroepen.

## Installatie

1. Download de pluginbestanden en plaats deze in `/wp-content/plugins/nlpo-api/`
2. Activeer de plugin via WordPress
3. Ga naar **Instellingen → NLPO API** om de plugin te configureren

## Configuratie

De plugin wordt geconfigureerd via de WordPress admin onder **Instellingen → NLPO API**. De volgende instellingen zijn beschikbaar:

### Plausible Analytics
- **Plausible API-URL**: De API-URL van je Plausible-installatie (bijv. `https://plausible.io/api`)
- **Site-ID**: Het website-ID in Plausible Analytics
- **API-token**: Een Plausible API-token met leestoegang

### API-instellingen
- **Endpoint-token**: Een zelf gekozen token om het NLPO-endpoint te beveiligen
- **Cacheduur**: Hoe lang data gecachet blijft in seconden (standaard: 3600)
- **Debug-modus**: Schakel uitgebreide logging naar de PHP-errorlog in

## Gegevensstructuur

De plugin levert artikelgegevens aan in het door NLPO gespecificeerde formaat:

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

Deze implementatie is gebaseerd op deze specificaties: https://www.nlpo.nl/wp-content/uploads/2024/07/240711-PRD-_-Custom_API-v2-.pdf

## Technische informatie

Het endpoint is beschikbaar via:
```
GET /wp-json/zw/v1/nlpo?token=jouw-api-token&from=2025-01-01&to=2025-01-31
```

Parameters:
- `token` (verplicht): Je API-token
- `from` (optioneel): Startdatum (JJJJ-MM-DD)
- `to` (optioneel): Einddatum (JJJJ-MM-DD)

Zonder datumparameters worden artikelen van de afgelopen 7 dagen teruggegeven. Standaard worden gegevens uit Plausible Analytics één uur in de cache bewaard om overbelasting van de service te voorkomen.

## Tags en regio's

De plugin controleert of berichten een 'regio'-taxonomie hebben:
- Als een bericht de 'regio'-taxonomie heeft, worden deze termen gebruikt voor het 'tags'-veld
- Als een bericht geen 'regio'-taxonomie heeft, worden de standaard WordPress-tags gebruikt
- Als beide niet beschikbaar zijn, wordt een lege array teruggegeven

## Licentie

Deze plugin is gelicenseerd onder de MIT-licentie.

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