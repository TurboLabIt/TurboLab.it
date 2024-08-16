# [Gestione degli asset frontend](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/assets-frontend.md)


## Asset "regolari"



## Asset del tema

La cartella [public/assets](https://github.com/TurboLabIt/TurboLab.it/tree/main/public/assets) contiene alcune risorse del tema Newspark che non possono essere gestite in altro modo.

### main.js

JS principale (custom) del tema.


### [StellarNav.js](https://github.com/vinnymoreira/stellarnav)

Nel CSS è indicata essere in uso la versione `2.5.0`. Non ci sono altri riferimenti di versione sul sito.

- ✅ Il JS è originale
- 🇮🇳 Il CSS è stato modificato

Ho provato ad aggiungere il repo via Yarn:

````shell
yarn add stellarnav@https://github.com/vinnymoreira/stellarnav.git
````

Ma non funziona, perchè manca il `package.json`:

> Error: Manifest not found

Il JS che versionato in `public/assets` è dunque quello fornito da Newspark.
