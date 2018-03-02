# IRiS - Intelligente Rettung im SmartHome

Verwendung des Moduls:
- Installation in IP-Symcon via Module Control
- IRiS Instanz erstellen
- Daten auf der Modulseite eintragen
- Anfragen an \<webfront-Adresse\>/hook/iris via POST mit application/json content

#### Beispiel (Postman Anfrage):
````
POST /hook/iris HTTP/1.1
Host: 127.0.0.1:3777
Content-Type: application/json
Cache-Control: no-cache
Postman-Token: c3ea5bc5-d89a-fdbe-551d-5e02e7441a4b

{"jsonrpc": "2.0", "method": "getObjectList", "id": 1}
````