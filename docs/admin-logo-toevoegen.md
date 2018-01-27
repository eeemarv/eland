# Logo toevoegen

Sla je logo op als png of gif met ongeveer 80 pixels hoogte in de Documenten in eLAND.

Creëer een .css met je favoriete teksteditor om het logo in te voegen. Hier een voorbeeld voor een .png van 170 x 80 pixels:

```css
div.logo {
  background-image: url("http://doc.letsa.net/a_d_5676efbecd722d5c008b459d.png");
  background-position: center;
  background-repeat: no-repeat;
  padding-left: 100px;
  padding-top: 50px;
  background-size: 60%;
}
```

Op de plaats van `http://doc.letsa.net/a_d_5676efbecd722d5c008b459d.png` in het voorbeeld hierboven, plak je de url van je logo. En pas de padding-top, padding-left en de background-sizing aan aan de dimensies van je logo.

Sla dan ook dit css bestand op in de Documenten van eLAND en kopiëer de url naar de 'css' instelling.
