UniMapper Flexibee extension
============================

Flexibee API integration with [UniMapper](http://unimapper.github.io).

[![Build Status](https://secure.travis-ci.org/unimapper/flexibee.png?branch=master)](http://travis-ci.org/unimapper/flexibee)

# Usage

```php
$config = [
    // Required
    "host" => "http://localhost:5434"
    "company" => "name"

    // Optional authentization
    "user" => ,
    "password" => ,

    // Optional SSL version
    "ssl_version" => 3
];
$adapter = new UniMapper\Flexibee\Adapter($config);

// Create new contacts
$response = $adapter->put("adresar.json", ["adresar" => ["sumCelkem" => ....]);

// Read every created contact detail
foreach ($response->results as $result) {
    $adapter->get("adresar/" . $result->id . ".json");
}
```

For more inromation see the docs on official [Flexibee](http://www.flexibee.eu) site.

# Příklady z praxe

Takhle může být zadefinovaná evidence *objednavka-prijata* v plné kráse. Seznam všech lze najít například na https://demo.flexibee.eu/c/demo/evidence-list

## Entity
```
<?php

namespace ProjectName\Entity;

/**
 * @adapter Flexibee(objednavka-prijata)
 *
 * @property string             $id                 m:primary m:map-by(id)
 * @property string             $code               m:map-by(kod)
 * @property string             $status             m:map-by(stavUzivK) m:enum(self::STATE_*)
 * @property string             $staff              m:map-by(zodpOsoba)
 * @property-read string        $staffFullName      m:map-by(zodpOsoba@showAs)
 * @property Date               $dateCreated        m:map-by(datVyst)
 * @property-read double        $basePrice          m:map-by(sumZklCelkem)
 * @property boolean            $cancel             m:map-by(storno)
 * @property Attachment[]       $attachments        m:map-by(prilohy)
 * @property boolean            $itemsRemoveAll     m:map-by(polozkyObchDokladu@removeAll)
 * @property EvidenceItem[]     $evidenceItems      m:map-by(polozkyObchDokladu)
 * @property boolean            $tagsRemoveAll      m:map-by(stitky@removeAll)
 * @property array              $tags               m:map-by(stitky) m:map-filter(mapStitky|unmapStitky)
 * @property string             $addressBookId      m:map-by(firma)
 * @property string             $city               m:map-by(mesto)
 * @property string             $email              m:map-by(kontaktEmail)
 * @property string             $companyName        m:map-by(nazFirmy)
 * @property string             $phone              m:map-by(kontaktTel)
 * @property string             $postCity           m:map-by(faMesto)
 * @property string             $postCompanyName    m:map-by(faNazev)
 * @property string             $postStreet         m:map-by(faUlice)
 * @property string             $postZipCode        m:map-by(faPsc)
 * @property string             $street             m:map-by(ulice)
 * @property string             $zipCode            m:map-by(psc)
 * @property string             $companyId          m:map-by(ic)
 * @property string             $vatId              m:map-by(dic)
 * @property boolean            $mainAddress        m:map-by(postovniShodna)
 * @property array              $externalIds        m:map-by(external-ids)
 * @property Offer[]            $offers             m:assoc(M:N) m:assoc-by(vazby|typVazbyDokl.obchod_obchod_hla|a)
 * @property Invoice[]          $advanceInvoices    m:assoc(M:N) m:assoc-by(vazby|typVazbyDokl.obchod_zaloha_hla|b)
 * @property Invoice[]          $cashInvoices       m:assoc(M:N) m:assoc-by(vazby|typVazbyDokl.obchod_faktura_hla|b)
 * @property-read Invoice[]     $invoices           m:computed
 * @property-read Attachment[]  $attachments        m:assoc(1:N) m:assoc-by(prilohy)
 */
class Order extends \UniMapper\Flexibee\Entity
{

    const STATE_NEW = null,
          STATE_UNSPECIFIED = "stavDoklObch.nespec",
          STATE_FORAPPROVAL = "stavDoklObch.pripraveno",
          STATE_APPROVED = "stavDoklObch.schvaleno",
          STATE_ACCEPTED_PARTIALLY = "stavDoklObch.castVydano",
          STATE_ACCEPTED = "stavDoklObch.vydano",
          STATE_FINISHED_PARTIALLY = "stavDoklObch.castHotovo",
          STATE_FINISHED = "stavDoklObch.hotovo",
          STATE_CANCELED = "stavDoklObch.storno",

    /**
     * Compute invoice
     *
     * @return \UniMapper\EntityCollection
     */
    public function computeInvoices()
    {
        $invoices = new \UniMapper\EntityCollection("Invoice");

        foreach ($this->cashInvoices as $invoice) {
            $invoices[] = $invoice;
        }
        foreach ($this->advanceInvoices as $invoice) {
            $invoices[] = $invoice;
        }
        return $invoices;
    }
```

#### Doporučení
- **Identifikátory (zde například ID objednávky, addressBookId, ...) definujte VŽDY typu string.** Unimapper preferuje textový identifikátor z Flexibee před číselným. Tj. pokud si necháme vylistovat objednávky z Flexibee, jako ID dorazí něco ve stylu *code:OBP0001/2015*. Pokud Flexibee u dané evidence nedrží textový identifikátor, vrátí se číselný (avšak typově jako string).
- Proměnné, které si Flexibee vypočítává samo (například suma za celou objednávku) a nebo je zakázáno je do Flexibee posílat (viz. dokumentace Flexibee) je vhodné označit jako *@property-read*. Unimapper pak nebude tyto property do Flexibee zapisovat, pouze je z Flexibee načte.
- Pokud je v dokumentaci Flexibee proměnná definovaná jako *date*, nastavte typ i zde na *Date*. Nepoužívejte *DateTime*, vyvarujete se problémů při filtraci záznamů dle datumu (např. vypiš všechny včerejší objednávky).

#### Tipy
- Díky *m:enum* lze hlídat hodnoty ve výčtových typech. Unimapper vyhodí výjimku, pokud se hodnota nenachází v datech, která se vrací z Flexibee / posílají do Flexibee.
- Štítky (zde tags) si lze jednoduše převést do array pomocí *m:map-filter(mapStitky|unmapStitky)* a pak s nimi jednodušeji pracovat. Naopak při zápisu se z array vhodně přetransformují do podoby, kterou Flexibee akceptuje.
- Pokud chcete načítat i externí identifikátory, lze ala příklad použít *$externalIds*.
- Pomocí *m:assoc(M:N)* dokážete skoro kouzla. Například v *$advanceInvoices* budete mít rovnou kolekci navázaných zálohovek k této objednávce a v *$cashInvoices* kolekci navázaných faktur. Pokud byste to rovnou chtěli pohromadě v jedné kolekci (proformy i faktury), můžete využít další vychytávku Unimapperu a to je "computed" property.
- Pomocí *m:computed* můžete zařídit, že jakmile k takové property přistoupíte, bude obsahovat přesně ten obsah, který potřebujete. V příkladu s tím souvisí metoda *computeInvoices()*.
- Pokud upravujeme existující objednávku, je vhodné nastavit *$itemsRemoveAll* na *TRUE* a zároveň poslat všechny položky objednávky znovu (*$evidenceItems*). V opačném případě se nám budou s každou úpravou množit na objednávce položky (viz. dokumentace Flexibee).

## Repository
Pokud pak máme repository zadefinovanou takto:

```
namespace ProjectName\Repository;

class OrderRepository extends \UniMapper\Repository
{

```

můžeme tam vytvořit třeba tyhle metody:

- Přepíše "vlastníka" objednávky za předpokladu, že aktuálně patří adminovi a je v určitém stavu.

```
public function assignStaffToOrder($orderId, $staff)
{
    $this->query()
        ->update(array("staff" => $staff))
        ->where("id", "=", $orderId)
        ->where("status", "=", Order::STATE_FORAPPROVAL)
        ->where("staff", "=", "code:admin")
        ->run($this->connection);
}
```

- Pokud si chcete formou štítků poznamenávat důvod storna, může přijít vhod následující metoda, která vrátí seznam štítků. Vynikající je v tomto ohledu možnost zapnout kešování Unimapperu! Proč se ptát na neměnný číselník stále dokola? Každý dotaz do Flexibee má nějako tu režii.

```
public function getCancelReasons()
{
    return \Fik\Entity\Tag::query()
        ->select()
        ->where("tagGroup", "=", "code:STORNO")
        ->cached(
            true,
            [\UniMapper\Cache\ICache::TAGS => [self::CACHE_TAG_CODEBOOK]]
        )
        ->run($this->connection);
}
```

- Nevyhovuje standardní *save()*? Můžeme ji přetížit a udělat nějakou tu věc navíc. Tady třeba nastavit dnešní datum vzniku objednávky, pokud jde o novou objednávku (*ID === null*).

```
public function save(\UniMapper\Entity $order)
{
    if ($order->id === null) {
        $order->dateCreated = new \DateTime();
    }

    parent::save($order);
}
```

- Některá workflow Flexibee vyžadují trochu více snahy. Pokud chceme z objednávky udělat fakturu, budeme potřebovat určitě tuto pasáž kódu (dokumence Flexibee napoví):

```
$structure = array(
    "objednavka-prijata" => array(
        "@id" => "{$orderId}",
        "realizaceObj" =>
        array("@type" => "faktura-vydana",
            "polozkyObchDokladu" => $polozkyDokladu
        )
    )
);

$invoiceCreated = $this->getAdapter("Flexibee")->put(
    "objednavka-prijata.json",
    $structure
);
```

- Počty objednávek ke schválení? Žádný problém :-)

```
public function getTotalCountForApprove()
{
    $result = $this->query()
        ->count()
        ->where("status", "=", Order::STATE_FORAPPROVAL)
        ->where("staff", "=", "code:admin")
        ->run($this->connection);

    return $result + $this->query()
        ->count()
        ->where("documentType", "=", Order::DOCTYPE_CARD)
        ->where("staff", "=", "code:admin")
        ->run($this->connection);
}
```

- Vytvoření jednoduché objednávky:

```
    public function createTestOrder()
    {
        $order = new Order;
        $order->documentType = Order::DOCTYPE_CONSIGNMENT;
        $order->addressBookId = "code:FIRMA";

        $items = [];
        
        $item = new EvidenceItem; // entita vázaná na evidenci "objednavka-prijata-polozka"
        $item->itemPriceList = "code:KRABICE_DROG"; // property objednavka-prijata-polozka.cenik
        $item->itemAmount = 2.0; // property objednavka-prijata-polozka.mnozMj
        $items[] = $item;
        
        $item = new EvidenceItem;
        $item->itemPriceList = "code:KRABICE_ALKOHOLU";
        $item->itemAmount = 1.0;
        $items[] = $item;

        $order->evidenceItems = new \UniMapper\EntityCollection(
            "EvidenceItem", $items
        );

        $this->save($order);
        
        // v $order->id budu mít v tento moment identifikátor objednávky z Flexibee, tj. třeba "code:OBP0001/2015"
    }
```

- Vrácení PDF definované objednávky:

```
public function getOrderPdf($orderId)
{
    return $this->getAdapter("Flexibee")->get(
        "objednavka-vydana/" . rawurlencode($orderId) . ".pdf",
        "application/pdf"
    );
}
```

- Vytažení objednávky včetně asociací:

```
public function getOrder($orderId)
{
    $query = $this->query()->selectOne($orderId)->associate(["advanceInvoices", "cashInvoices"]);
    $order = $query->run($this->connection);

    return $order;
}
```

- Hledání textu v poznámce určitých objednávek:

```
$invoices = $this->query()
    ->select()
    ->where("note", "LIKE", "%" . $tentoTextHledame . "%")
    ->where("documentType", "IN", ["code:PRIMA", "code:NEPRIMA"])
    ->orderBy("id", "desc")
    ->limit(10)
    ->run($this->connection);
```

## Práce nad repository

I tady se toho nabízí spousta. Například tohle může být základ pro skript, který má odesílat nezaplacené proformy:

```
// vyber vsechny, co nejsou uhrazene a stornovane
$filter = [
    "canceled" => ["=" => false], // = faktura-vydana.storno
    "paymentStatus" => [ // = faktura-vydana.stavUhrK
        "!" => [
            Entity\Invoice::PAYMENT_STATUS_PAIDMANUALLY, // = faktura-vydana.stavUhr.uhrazenoRucne
            Entity\Invoice::PAYMENT_STATUS_PAID // = faktura-vydana.stavUhr.uhrazeno
        ]
    ]
];

// prvni upominka dva dny po splatnosti
foreach ($this->invoiceRepository->find(
    $filter + [
        "documentType" => ["=" => Entity\Invoice::DOCTYPE_PROFORMA], // = faktura-vydana.typDokl = "code:ZÁLOHA"
        "firstReminder" => ["=" => null], // faktura-vydana.datUp1
        "dueDate" => [
            "<" => new \DateTime("-1 day") // faktura-vydana.datSplat
        ]
    ]
) as $invoice) {
```

#### Doporučení pro úpravu stavů v entitě

Občas se nám může naskytnout situace, kdy máme například celou entitu objednávka načtenou a po nějakých těch operacích dospějeme k tomu, že je potřeba upravit pouze stav objednávky:

```
$order = $this->orderRepository->findOne("code:OBP0001/2015);
...
nějaké ty operace
...
$order->status = "stavDoklObch.hotovo";
$this->orderRepository->save($order);
```

**Výše uvedený postup je však nevhodný!**

Naopak to doporučujeme řešit takto:

```
$order = $this->orderRepository->findOne("code:OBP0001/2015);
...
nějaké ty operace
...
$this->orderRepository->save(
    new ProjectName\Entity\Order(
        ["id" => $order->id, "status" => "stavDoklObch.hotovo"]
    )
);
```

Takhle zajistíme, že Flexibee nebude celou objednávku přepočítávat (neboť v *$order->evidenceItems* budou i její položky! a že opravdu pouze změní status u definované objednávky (*id*).
