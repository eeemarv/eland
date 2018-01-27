# eLAS interLETS koppeling maken

Voor interLETS is het vereist dat de groep geconfigureerd is als 'LETS' en 'interLETS' ingeschakeld is. Zie hiervoor in de configuratie onder "Systeem".

Onderstaande tekst werd overgenomen van [de eLAS documentatie](http://old.elasproject.org/content/hoe-maak-ik-een-interlets-koppeling).

Hier en daar werd een kleine aanpassing gedaan vanwege de eLAND context.

## Hoe maak ik een eLAS interLETS koppeling

(door gvansanden op do, 03/01/2012 - 12:46)

### admin

Elke groep kan zelf een interLETS koppeling met een andere groep opzetten in enkele stappen.
Maak je eigen installatie klaar

* Log in op eLAS (of eLAND) als admin
* In het beheerblok, kies Apikeys > Apikey toevoegen  Voer de naam van de groep in
* Kopieer de apikey in het overzichtscherm naar een tekstbestandje of een e-mail
* Kies Gebruikers > Toevoegen en maak een account voor de andere groep aan met de groepnaam als naam (bv LETS Geel), een unieke letscode en rechten interlets, status extern.  Voer ook een pre-shared key in, genereer hiervoor een veilig wachtwoord dat lang genoeg is (20 karakters) op bv [Wachtwoord Generator](http://www.onlinewachtwoordgenerator.nl/)

### Stuur je gegevens door naar de andere groep

Stuur een mailtje naar de beheerder van de andere groep met volgende gegevens duidelijk vermeld:

* API Key (uit stap 3 hierboven)
* LETS code (uit stap 4 hierboven)
* Preshared key (uit stap 4 hierboven)
* De URL van je installatie (uit de adresbalk van je browser, bv. `http://elas.letsgeel.org`)

Een voorbeeld van zo'n mailtje kan zijn:

    Beste Jef

    Hier zijn de gegevens voor een interletskoppeling tussen jullie groep (LETS Test) met LETS Geel.

    Apikey: d8e8fca2dc0f896fd7cb4cb0031ba249
    LETSCode: LT000
    Preshared key: KHQyhOv2mFinbr68QDyV
    URL: http://elas.letsgeel.org

    Als wij de gegevens van jullie kant hebben ontvangen werkt de koppeling in 2 richtingen.

### De mail die je van de andere groep kreeg verwerken

Van de groep waarmee je wil koppelen heb je een gelijkaardige mail als hierboven ontvangen, in onderstaande instructies staan de waardes tussen <> voor dingen die je zelf moet invullen (zonder <> natuurlijk).

* Log in als admin
* In het beheer blok, kies LETS Groepen (eLAND: InterLETS) > Groep toevoegen
* Vul volgende velden in:
* Group naam: naam van de groep waarmee je koppelt, bv LETS Geel
* (niet in eLAND:) korte naam: korte naam zonder hoofdletters of spaties van de groep waarmee je koppelt, bv letsgeel
* (niet in eLAND:) prefix: LEEG laten
* API Method: elassaop (standaardwaarde)
* Remote API key: de apikey uit de mail die je aankreeg
* Lokale LETS code: de LETS code die je aanmaakte in 'maak je eigen installatie klaar'
* Remote LETS code: de LETS code uit de mail die je aankreeg
* URL: de URL uit de mail die je aankreeg
* (niet in eLAND:) SOAP URL: de URL uit de mail die je aankreeg met /soap erachter, bv `http://elas.letsgeel.org/soap`
* Preshared key: de preshared key uit de mail die je aankreeg

Na het afronden van bovenstaande stappen aan de 2 kanten is de koppeling actief.
