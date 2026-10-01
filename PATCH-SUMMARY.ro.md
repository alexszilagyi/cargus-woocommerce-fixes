# Rezumat al corecturilor pentru Cargus 1.6.0

Acesta este un patch neoficial, făcut de un utilizator, pentru pluginul Cargus pentru WooCommerce, versiunea 1.6.0 (GPL-2.0+). Nu este afiliat cu Cargus. Detaliile complete, în engleză, sunt în [README.md](README.md). Fiecare corectură este un commit separat.

## Problema principală: clientul nu își poate completa strada

Opțiunea „Selectare stradă” este activă. Clientul alege o localitate cu coduri poștale pe stradă (Oradea, Cluj-Napoca, București etc.). Câmpul „Strada” devine atunci o listă **goală**. Clientul nu poate alege nicio stradă și nici nu poate scrie una, deci nu poate plasa comanda.

Cauza: `public/js/cargus.js` apelează direct `.select2()`, dar nu declară select2/selectWoo ca dependență. Multe teme și pluginuri de optimizare scot select2 din pagina de checkout. În acest caz apare eroarea `TypeError: $(...).select2 is not a function`, iar câmpul rămâne o listă goală. Endpointul `cargus_get_streets` funcționează corect, doar că nu mai este apelat.

În câmpul „Numar”, textul `1` este doar placeholder. Arată ca o valoare completată, dar câmpul este de fapt gol și apare marcat cu roșu.

## Ce corectează fiecare commit

1. **Câmpurile Strada / Numar.**
   - Funcționează și fără select2: câmp text cu sugestii native (`<datalist>`) din nomenclatorul Cargus.
   - Acceptă și o stradă care lipsește din nomenclator.
   - Când strada este aleasă din listă, codul poștal și `StreetId` din AWB se completează în continuare.
   - `address_1` se completează și pe server, ca rezervă.
   - Placeholderele devin `ex: Unirii` / `ex: 12`.
   - Strada, ID-ul străzii și numărul se salvează acum pe comandă. Variabila `$cargus_options` nu era încărcată în `cargus_before_checkout_create_order()`.
2. **Sesiuni PHP.**
   - `session_start()` nu mai rulează pe `init` la fiecare cerere. Asta elimină avertismentul *„Session cannot be started after headers have already been sent”*, apărut de 6.134 de ori într-o săptămână.
   - Dispare și cookie-ul `PHPSESSID` de pe fiecare pagină, care bloca cache-ul.
3. **Răspunsul API la calculul tarifului.** Erorile API vin ca listă JSON, iar codul citea `->Subtotal` dintr-un array. Prețul `null` ajungea apoi la 0, iar livrarea Cargus apărea **gratuită** la orice eroare a API-ului. Acum, când răspunsul nu conține un preț, metoda Cargus nu mai este afișată, iar eroarea este scrisă în log.
4. **Eroare fatală la salvarea comenzii (generare AWB).** O greutate de colet goală (`""`) producea `Unsupported operand types: int + string`, iar comanda nu se mai putea salva. Greutatea cu virgulă (`2,5`) era citită ca `2`.
5. **Eroare fatală la salvarea setărilor.** Apărea `get_pickup_locations() on null` când URL-ul webservice sau cheia API lipseau din formular.
6. **Punctele de ridicare.**
   - Opțiunea `cargus_pickup_points` nu mai este scrisă de plugin, așa că buclele `foreach` rulau pe `false`.
   - Din acest motiv, livrarea pre10/pre12 nu recunoștea expeditorii din București, iar „prețul dinamic” trimitea `FromLocalityId = null`.
   - Acum se folosesc punctele din `PickupLocations`, păstrate în cache.

Comportamentul este neschimbat când API-ul răspunde corect, select2 este prezent și greutățile sunt numerice. Fișierele modificate sunt compatibile cu PHP 7.4–8.5.
