# eLAS interSysteem koppeling maken

Voor interSysteem is het vereist dat het Systeem geconfigureerd is als Tijdsbank
(het is een munt met tijdsbasis) en dat de 'interSysteem' functionaliteit ingeschakeld is. Zie hiervoor in de configuratie onder "Systeem".

Onderstaande tekst werd aangepast vanuit [de eLAS documentatie](http://old.elasproject.org/content/hoe-maak-ik-een-interlets-koppeling). (orgineel geschreven door gvansanden op do, 03/01/2012 - 12:46)

**In eLAS wordt interSysteem interLETS genoemd, maar omdat LETS geen munt
met tijdsbasis is en omdat LETS expliciet ontworpen is als middel om de eigen gemeenschap onafhankelijker van externe economische invloed te maken, worden
deze verbindingen in eLAND interSysteem genoemd.** (Zie de [LETS-documentatie](https://manual.letsa.net/nl/1.3.html))

## Hoe maak ik een eLAS interSysteem koppeling

### Maak je eigen Systeem klaar (admin)

Elk systeem kan zelf een interSysteem koppeling met een ander systeem opzetten in enkele stappen.

* Log in je Systeem in eLAND (of eLAS) als admin
* In het beheerblok, kies Apikeys > Apikey toevoegen  Voer de naam in van het andere Systeem bij de Commentaar.
* Kopieer de Apikey in het overzichtscherm naar een tekstbestandje of een e-mail
* Kies Gebruikers > Toevoegen en maak een Account voor het andere Systeem aan. Vul de naam van het Systeem in bij Gebruikersnaam (bv Tijdsbank Geel), een unieke Account Code en rechten "interSysteem" (interlets in eLAS), status extern. Voer ook een Preshared Key in. Genereer hiervoor een veilig wachtwoord dat lang genoeg is (20 karakters) op bv [Wachtwoord Generator](http://www.onlinewachtwoordgenerator.nl/)

### Stuur je gegevens door naar de beheerders van het andere Systeem

Stuur een mailtje naar de beheerders van andere Systeem met volgende gegevens duidelijk vermeld:

* API Key (uit stap 3 hierboven)
* Account Code (uit stap 4 hierboven)
* Preshared Key (uit stap 4 hierboven)
* De URL van je Systeem (uit de adresbalk van je browser, bv. `http://elas.tijdsbankgeel.org`)

Een voorbeeld van zo'n mailtje kan zijn:

    Beste Jef

    Hier zijn de gegevens voor een interSysteem-verbinding tussen jullie Systeem (Tijdsbank Paars) met ons Systeem, Tijdsbank Geel.

    Apikey: d8e8fca2dc0f896fd7cb4cb0031ba249
    Account Code: TT000
    Preshared Key: KHQyhOv2mFinbr68QDyV
    URL: http://elas.tijdsbankgeel.org

    Als wij de gegevens van jullie kant hebben ontvangen werkt de interSysteem-verbinding in 2 richtingen.

### De mail die je van het andere Systeem kreeg verwerken

Van het Systeem waarmee je wil koppelen heb je een gelijkaardige mail als hierboven ontvangen. In onderstaande instructies staan de waardes tussen <> voor dingen die je zelf moet invullen (zonder <> natuurlijk).

* Log in als admin
* In het beheer blok, kies InterSysteem > Toevoegen (in eLAS: LETS Groepen > Groep toevoegen)
* Vul volgende velden in:
  * Systeem Naam: naam van het Systeem waarmee je een verbinding legt, bv Tijdsbank Geel
  * (niet in eLAND) korte naam: korte naam zonder hoofdletters of spaties van het Systeem waarmee je koppelt, bv tijdsbankgeel
  * (niet in eLAND:) prefix: LEEG laten
  * API Method: elassaop (standaardwaarde)
  * Remote API Key: de apikey uit de mail die je aankreeg
  * Lokale Account Code: de Account Code die je aanmaakte in 'maak je eigen Systeem klaar'
  * Remote code: de Account Code uit de mail die je aankreeg
  * URL: de URL uit de mail die je aankreeg
  * (niet in eLAND:) SOAP URL: de URL uit de mail die je aankreeg met /soap erachter, bv `http://elas.tijdsbankgeel.org/soap`
  * Preshared Key: de Preshared Key uit de mail die je aankreeg

Na het afronden van bovenstaande stappen aan de 2 kanten is de interSysteem-verbinding actief.
