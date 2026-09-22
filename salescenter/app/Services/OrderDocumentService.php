<?php

declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use App\Models\OrderRepository;
use InvalidArgumentException;

final class OrderDocumentService
{
    private $repo;
    public function __construct(OrderRepository $repo) { $this->repo=$repo; }
    /** Paragon z NIP nabywcy jest fakturą uproszczoną — art. 106e ust. 5 pkt 3 ustawy o VAT: do 450 zł (100 EUR) brutto. */
    public const RECEIPT_NIP_LIMIT_CENTS=45000;
    public const RECEIPT_NIP_LIMIT_EUR_CENTS=10000;

    /** Prawidłowy NIP nabywcy odczytany z bloku danych nabywcy albo null. */
    public static function receiptBuyerNip(string $buyer): ?string
    {
        return KsefService::parseBuyer($buyer)['nip']??null;
    }

    public static function assertReceiptNipLimit(?string $nip,int $grossCents,string $currency): void
    {
        if ($nip===null || $nip==='') { return; }
        $eur=strtoupper($currency)==='EUR';
        if ($grossCents>($eur?self::RECEIPT_NIP_LIMIT_EUR_CENTS:self::RECEIPT_NIP_LIMIT_CENTS)) {
            throw new InvalidArgumentException('Paragonu z NIP nabywcy nie można wystawić na kwotę powyżej '.($eur?'100 EUR':'450 zł').' brutto (faktura uproszczona, art. 106e ust. 5 pkt 3 ustawy o VAT). Wystaw fakturę VAT albo usuń NIP z danych nabywcy.');
        }
    }
    /** First series of the kind; creates a sensible default when none exists yet. */
    public static function defaultSeries(Database $db,string $kind): array
    {
        $series=$db->fetch('SELECT * FROM om_series WHERE kind=:kind ORDER BY id LIMIT 1',['kind'=>$kind]);
        if ($series) { return $series; }
        $labels=['invoice'=>['Faktury','FV'],'receipt'=>['Paragony','PAR'],'invoice_correction'=>['Korekty faktur','KOR-FV'],'receipt_correction'=>['Korekty paragonów','KOR-PAR']];
        if (!isset($labels[$kind])) { throw new InvalidArgumentException('Nieprawidłowy rodzaj dokumentu.'); }
        $id=(int)$db->insert('om_series',['name'=>$labels[$kind][0],'kind'=>$kind,'pattern'=>$labels[$kind][1].'/{YYYY}/{N}','next_number'=>1,'fiscal_printer_id'=>null]);
        return $db->fetch('SELECT * FROM om_series WHERE id=:id',['id'=>$id]);
    }
    /** Seller snapshot field => series document setting. */
    public const SELLER_FIELDS=['name'=>'seller_name','address'=>'seller_address','nip'=>'seller_nip','bank'=>'seller_bank','bank_name'=>'seller_bank_name','swift'=>'seller_swift','email'=>'seller_email','phone'=>'seller_phone','regon'=>'seller_regon','krs'=>'seller_krs','bdo'=>'seller_bdo'];
    /** Document lines built from the order: items, delivery and a balancing line for discounts. */
    public static function orderPayload(OrderRepository $repo,array $order,string $buyerKind,string $requestKey,array $series=[]): array
    {
        $settings=json_decode((string)($series['document_settings_json']??''),true)?:[];
        $defaultVat=(string)(($repo->setting('document_defaults')['vat']??'23'));
        if (!in_array($defaultVat,['23','8','5','0','zw','np'],true)) { $defaultVat='23'; }
        $items=[]; $sum=0;
        foreach ((array)($order['details']['items']??[]) as $item) {
            $vat=($settings['vat_source']??'order')==='static'?(string)($settings['vat_rate']??$defaultVat):(string)($item['vat']??$defaultVat); if (!in_array($vat,['23','8','5','0','zw','np'],true)) { $vat=$defaultVat; }
            $quantity=max(0,(int)($item['quantity']??0)); $unit=(int)($item['unit_cents']??0); $sum+=$quantity*$unit;
            $items[]=['name'=>(string)($item['name']??'Produkt'),'quantity'=>$quantity,'price'=>number_format($unit/100,2,'.',''),'vat'=>$vat,'sku'=>(string)($item['sku']??''),'ean'=>(string)($item['ean']??$item['gtin']??'')];
        }
        $shipping=(int)($order['details']['shipping_cents']??0);
        if ($shipping>0) {
            $shippingVat=($settings['shipment_vat_type']??'order')==='static'?(string)($settings['shipment_vat']??$defaultVat):$defaultVat;
            $shippingName=trim((string)($settings['shipment_name']??'Dostawa'))?:'Dostawa';
            if (!empty($settings['add_shipment_name']) && !empty($order['details']['delivery'])) { $shippingName.=' — '.substr((string)$order['details']['delivery'],0,100); }
            $items[]=['name'=>$shippingName,'quantity'=>1,'price'=>number_format($shipping/100,2,'.',''),'vat'=>$shippingVat]; $sum+=$shipping;
        }
        $difference=(int)$order['total_cents']-$sum;
        if ($difference!==0) { $items[]=['name'=>$difference<0?'Rabat / korekta wartości':'Pozostałe opłaty','quantity'=>1,'price'=>number_format($difference/100,2,'.',''),'vat'=>$defaultVat]; }
        return ['request_key'=>$requestKey,'buyer'=>self::buyerText($order),'recipient'=>self::recipientText($order),'items'=>$items];
    }
    /**
     * Buyer block for invoices and receipts: billing data from the order, or the delivery address
     * when the order has no separate billing data. Format matches KsefService::parseBuyer().
     */
    public static function buyerText(array $order): string
    {
        $billing=(array)($order['details']['invoice_form']??[]);
        $shipping=(array)($order['shipping_address']??[]);
        $hasBilling=trim((string)($billing['name']??'').(string)($billing['company']??'').(string)($billing['street']??''))!=='';
        $source=$hasBilling?$billing:$shipping;
        $company=$hasBilling?trim((string)($billing['company']??'')):'';
        $name=trim((string)($source['name']??''));
        $lines=[$company!==''?$company:($name!==''?$name:(string)($order['buyer_name']??''))];
        if ($company!=='' && $name!=='' && trim((string)($billing['nip']??''))==='') { $lines[]=$name; }
        if ($hasBilling && trim((string)($billing['nip']??''))!=='') { $lines[]='NIP: '.trim((string)$billing['nip']); }
        return implode("\n",array_filter(array_merge($lines,self::addressBlock($source)),'strlen'));
    }
    /** Delivery block printed next to the buyer: recipient, address and pickup point. */
    public static function recipientText(array $order): string
    {
        $shipping=(array)($order['shipping_address']??[]);
        $lines=array_merge([trim((string)($shipping['name']??''))],self::addressBlock($shipping));
        $pickup=trim((string)($order['details']['pickup']??''));
        if ($pickup!=='') { $lines[]='Punkt odbioru: '.$pickup; }
        $method=trim((string)($order['details']['delivery']??''));
        if ($method!=='') { $lines[]='Dostawa: '.$method; }
        return implode("\n",array_filter($lines,'strlen'));
    }
    private static function addressBlock(array $address): array
    {
        $street=trim(trim((string)($address['street']??'')).' '.trim((string)($address['building']??'')));
        $city=trim(trim((string)($address['postal_code']??'')).' '.trim((string)($address['city']??'')));
        $country=strtoupper(trim((string)($address['country']??'')));
        return [$street,$city,$country!=='' && $country!=='PL'?$country:''];
    }
    public static function calculate(array $items): array
    {
        if (!$items || count($items)>300) { throw new InvalidArgumentException('Dokument wymaga od 1 do 300 pozycji.'); }
        $net=0; $tax=0; $gross=0; $result=[];
        foreach ($items as $item) {
            $name=trim((string)($item['name']??''));
            $quantity=filter_var($item['quantity']??null,FILTER_VALIDATE_INT);
            $vat=strtolower((string)($item['vat']??''));
            if ($name==='' || $quantity===false || $quantity<0 || $quantity>100000 || !in_array($vat,['23','8','5','0','zw','np'],true)) { throw new InvalidArgumentException('Sprawdź nazwę, ilość i stawkę VAT każdej pozycji.'); }
            $unit=OrderNormalizer::money($item['price']??'');
            if ($unit< -100000000 || $unit>100000000) { throw new InvalidArgumentException('Cena poza dopuszczalnym zakresem.'); }
            $g=$quantity*$unit;
            $n=(int)round($g*100/(100+(int)$vat),0,PHP_ROUND_HALF_UP);
            $t=$g-$n;
            $row=['name'=>substr($name,0,300),'quantity'=>$quantity,'unit_cents'=>$unit,'vat'=>$vat,'net_cents'=>$n,'tax_cents'=>$t,'gross_cents'=>$g];
            foreach (['sku'=>50,'ean'=>20] as $code=>$max) { $value=trim((string)($item[$code]??'')); if ($value!=='') { $row[$code]=mb_substr($value,0,$max,'UTF-8'); } }
            $result[]=$row;
            $net+=$n; $tax+=$t; $gross+=$g;
        }
        return ['items'=>$result,'net_cents'=>$net,'tax_cents'=>$tax,'gross_cents'=>$gross];
    }
    public function issue(int $orderId,array $input,string $actor): int
    {
        $db=$this->repo->db();
        $key=(string)($input['request_key']??'');
        if (!preg_match('/^[a-f0-9]{32,80}$/D',$key)) { throw new InvalidArgumentException('Odśwież formularz dokumentu.'); }
        $documentId=$db->transaction(function () use ($orderId,$input,$actor,$db,$key) {
            $existing=$db->fetch('SELECT id,order_id FROM om_documents WHERE request_key=:k',['k'=>$key]);
            if ($existing) {
                if ((int)$existing['order_id']!==$orderId) { throw new InvalidArgumentException('Nieprawidłowy klucz dokumentu.'); }
                return (int)$existing['id'];
            }
            $lock=$db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite' ? '' : ' FOR UPDATE';
            $series=$db->fetch('SELECT * FROM om_series WHERE id=:id'.$lock,['id'=>(int)($input['series_id']??0)]);
            if (!$series) { throw new InvalidArgumentException('Wybierz serię numeracji.'); }
            $settings=json_decode((string)($series['document_settings_json']??''),true)?:[];
            $order=$this->repo->order($orderId);
            $seller=$this->repo->setting('seller');
            // Dane sprzedawcy pochodzą z ustawień serii; stare globalne dane sprzedawcy są tylko awaryjnym uzupełnieniem.
            foreach (self::SELLER_FIELDS as $sellerField=>$settingKey) {
                if (trim((string)($settings[$settingKey]??''))!=='') { $seller[$sellerField]=$settings[$settingKey]; }
            }
            if (empty($input['parent_id']) && (empty($seller['name']) || empty($seller['address']) || empty($seller['nip']))) { throw new InvalidArgumentException('Najpierw uzupełnij dane sprzedawcy (nazwa, NIP, adres) w ustawieniach serii „'.$series['name'].'”.'); }
            $buyer=trim((string)($input['buyer']??''));
            if ($buyer==='' && empty($settings['buyer_validation_disabled'])) { throw new InvalidArgumentException('Uzupełnij dane nabywcy.'); }
            if (mb_strlen($buyer)>2000) { throw new InvalidArgumentException('Dane nabywcy są za długie.'); }
            $recipient=array_key_exists('recipient',$input)?trim((string)$input['recipient']):null;
            if ($recipient!==null && mb_strlen($recipient)>2000) { throw new InvalidArgumentException('Dane dostawy są za długie.'); }
            $documentItems=$input['items']??[];
            if (empty($input['parent_id']) && ($settings['vat_source']??'order')==='static' && in_array((string)($settings['vat_rate']??''),['23','8','5','0','zw','np'],true)) {
                foreach ($documentItems as &$documentItem) { $documentItem['vat']=$settings['vat_rate']; }
                unset($documentItem);
            }
            $snapshot=self::calculate($documentItems);
            $parentId=null; $parent=null;
            $correction=in_array($series['kind'],['invoice_correction','receipt_correction'],true);
            if ($correction) {
                $parentId=(int)($input['parent_id']??0);
                $parent=$db->fetch('SELECT * FROM om_documents WHERE id=:id AND order_id=:o'.$lock,['id'=>$parentId,'o'=>$orderId]);
                $expected=$series['kind']==='invoice_correction'?'invoice':'receipt';
                if (!$parent || !in_array($parent['kind'],[$expected,$series['kind']],true) || trim((string)($input['reason']??''))==='' || mb_strlen(trim((string)$input['reason']))>500) { throw new InvalidArgumentException('Wybierz dokument właściwego typu i podaj powód korekty do 500 znaków.'); }
                $descendants=[$parentId=>true]; $latest=null;
                foreach ($db->fetchAll('SELECT id,parent_id,snapshot_json FROM om_documents WHERE order_id=:order_id AND parent_id IS NOT NULL ORDER BY id',['order_id'=>$orderId]) as $candidate) {
                    if (isset($descendants[(int)$candidate['parent_id']])) { $descendants[(int)$candidate['id']]=true; $latest=$candidate; }
                }
                $sourceRevision=(int)($latest['id']??$parentId);
                if (($input['operation']??'')==='document_correction' && (int)($input['source_revision_id']??0)!==$sourceRevision) { throw new InvalidArgumentException('Wystawiono nowszą korektę. Odśwież formularz i sprawdź aktualne dane.'); }
                $before=json_decode($latest['snapshot_json']??$parent['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
                $snapshot['before']=array_intersect_key($before,array_flip(['items','net_cents','tax_cents','gross_cents','buyer','recipient','seller','sale_date','issue_date','payment_due_date','currency','order_number','series_notes']));
                $snapshot['difference_cents']=$snapshot['gross_cents']-$before['gross_cents'];
                $snapshot['difference_net_cents']=$snapshot['net_cents']-(int)$before['net_cents'];
                $snapshot['difference_tax_cents']=$snapshot['tax_cents']-(int)$before['tax_cents'];
                $snapshot['parent_number']=$parent['number'];
            } elseif ($snapshot['gross_cents']!==(int)$order['total_cents']) {
                throw new InvalidArgumentException('Suma dokumentu musi zgadzać się z zamówieniem (uwzględnij dostawę i rabaty).');
            }
            $now=new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw'));
            $numberDate=$now;
            if ($correction && isset($input['issue_date'])) {
                $numberDate=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$input['issue_date'],new \DateTimeZone('Europe/Warsaw'));
                if (!$numberDate || $numberDate->format('Y-m-d')!==(string)$input['issue_date']) { throw new InvalidArgumentException('Nieprawidłowa data wystawienia korekty.'); }
            }
            $numbering=json_decode((string)($series['numbering_json']??''),true)?:[];
            $period=($numbering['format']??'')==='MONTHLY'?$numberDate->format('Y-m'):$numberDate->format('Y');
            $counter=(int)$series['next_number'];
            if (!empty($numbering['reset']) && !empty($series['numbering_period']) && $series['numbering_period']!==$period) { $counter=(int)$numbering['start']; }
            $digits=(string)$counter;
            if ($numbering) { $digits=str_pad($digits,(int)($numbering['length']??0),'0',STR_PAD_LEFT); }
            $number=strtr($series['pattern'],['{N}'=>$digits,'{YYYY}'=>$numberDate->format('Y'),'{MM}'=>$numberDate->format('m')]);
            $issueDate=$now->format('Y-m-d');
            $saleDate=substr((string)$order['ordered_at'],0,10);
            if (($settings['sale_date_source']??'order')==='issue_date') { $saleDate=$issueDate; }
            elseif (($settings['sale_date_source']??'order')==='payment') {
                $paidAt=(string)($order['details']['raw']['payment']['finishedAt']??$order['details']['payment_date']??'');
                if ($paidAt!=='') {
                    try { $saleDate=(new \DateTimeImmutable($paidAt))->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d'); }
                    catch (\Exception $e) { $saleDate=$issueDate; }
                } else { $saleDate=$issueDate; }
            }
            $paymentDays=(int)($settings['payment_term_days']??0);
            $dueDate=$paymentDays>0?$now->modify('+'.$paymentDays.' days')->format('Y-m-d'):null;
            if ($correction && $parent) {
                $parentSnapshot=json_decode($parent['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
                $seller=$parentSnapshot['seller']??$seller;
                foreach (['name'=>'seller_name','address'=>'seller_address','nip'=>'seller_nip','bank'=>'seller_bank'] as $sellerField=>$inputField) {
                    if (array_key_exists($inputField,$input)) { $seller[$sellerField]=trim((string)$input[$inputField]); }
                }
                if (empty($seller['name']) || empty($seller['address']) || empty($seller['nip'])) { throw new InvalidArgumentException('Uzupełnij dane sprzedawcy.'); }
                foreach (['name'=>200,'address'=>1000,'nip'=>30,'bank'=>200] as $sellerField=>$limit) { if (mb_strlen((string)($seller[$sellerField]??''))>$limit) { throw new InvalidArgumentException('Dane sprzedawcy są za długie.'); } }
                $dates=['sale_date'=>$saleDate,'issue_date'=>$issueDate,'payment_due_date'=>$dueDate];
                foreach ($dates as $inputField=>&$dateValue) {
                    if (array_key_exists($inputField,$input)) {
                        $dateInput=trim((string)$input[$inputField]);
                        $parsedDate=$dateInput!==''?\DateTimeImmutable::createFromFormat('!Y-m-d',$dateInput,new \DateTimeZone('Europe/Warsaw')):false;
                        if ($dateInput!=='' && (!$parsedDate || $parsedDate->format('Y-m-d')!==$dateInput)) { throw new InvalidArgumentException('Nieprawidłowa data dokumentu.'); }
                        $dateValue=$dateInput?:null;
                    } elseif (isset($parentSnapshot[$inputField])) { $dateValue=$parentSnapshot[$inputField]; }
                }
                unset($dateValue);
                $saleDate=$dates['sale_date']; $issueDate=$dates['issue_date']; $dueDate=$dates['payment_due_date'];
                if (!$saleDate || !$issueDate) { throw new InvalidArgumentException('Data sprzedaży i wystawienia są wymagane.'); }
            }
            $currency=(string)($correction?($input['currency']??$parentSnapshot['currency']??$order['currency']):$order['currency']);
            if (!preg_match('/^[A-Z]{3}$/D',$currency)) { throw new InvalidArgumentException('Nieprawidłowa waluta dokumentu.'); }
            $orderNumber=(string)($correction?($input['order_number']??$parentSnapshot['order_number']??$order['external_id']):$order['external_id']);
            if (mb_strlen($orderNumber)>190) { throw new InvalidArgumentException('Numer zamówienia jest za długi.'); }
            if ($correction && $currency!==(string)($parentSnapshot['currency']??$currency)) { throw new InvalidArgumentException('Zmiana waluty korekty wymaga przeliczenia kwot — wystaw korektę w walucie dokumentu źródłowego.'); }
            $paymentMethod=$correction?(string)($input['payment_method']??$parentSnapshot['payment_method']??''):(string)($order['details']['payment_method']??'');
            if (mb_strlen($paymentMethod)>150) { throw new InvalidArgumentException('Nazwa metody płatności jest za długa.'); }
            $amountPaid=$correction && isset($input['amount_paid'])?OrderNormalizer::money($input['amount_paid']):($correction?(int)($parentSnapshot['amount_paid_cents']??0):(int)($order['details']['amount_paid_cents']??0));
            if ($amountPaid<0 || $amountPaid>100000000000) { throw new InvalidArgumentException('Nieprawidłowa kwota zapłacona.'); }
            $splitPayment=$correction && ($input['operation']??'')==='document_correction'?!empty($input['split_payment']):($correction?(bool)($parentSnapshot['split_payment']??false):(($settings['split_payment']??'0')==='1'));
            if ($correction && mb_strlen((string)($input['series_notes']??''))>2000) { throw new InvalidArgumentException('Uwagi na dokumencie są za długie.'); }
            if ($series['kind']==='receipt') {
                $buyerNip=self::receiptBuyerNip($buyer);
                self::assertReceiptNipLimit($buyerNip,(int)$snapshot['gross_cents'],$currency);
                if ($buyerNip!==null) { $snapshot['buyer_nip']=$buyerNip; }
            }
            if ($recipient===null) { $recipient=$correction?(string)($parentSnapshot['recipient']??''):self::recipientText($order); }
            $snapshot+=['seller'=>$seller,'buyer'=>$buyer,'recipient'=>$recipient,'currency'=>$currency,'sale_date'=>$saleDate,'issue_date'=>$issueDate,'payment_due_date'=>$dueDate,'split_payment'=>$splitPayment,'reason'=>trim((string)($input['reason']??'')),'order_number'=>$orderNumber,'order_date'=>substr((string)($order['ordered_at']??''),0,10),'payment_method'=>$paymentMethod,'amount_paid_cents'=>$amountPaid,'settlement_state'=>'local','fiscalized'=>false,'series_notes'=>$correction?(string)($input['series_notes']??$parentSnapshot['series_notes']??''):(string)($numbering['notes']??'')];
            $id=(int)$db->insert('om_documents',['order_id'=>$orderId,'series_id'=>$series['id'],'kind'=>$series['kind'],'number'=>$number,'parent_id'=>$parentId,'request_key'=>$key,'snapshot_json'=>OrderRepository::json($snapshot),'created_at'=>gmdate('Y-m-d H:i:s')]);
            $db->update('om_series',['next_number'=>$counter+1,'numbering_period'=>$numbering?$period:null],'id=:id',['id'=>$series['id']]);
            $this->repo->event($orderId,'Zapisano dokument lokalny '.$number,$actor);
            $this->repo->automationEvent($orderId,'document_issued',['document_id'=>$id,'document_kind'=>(string)$series['kind']]);
            return $id;
        });
        $this->repo->flushAutomations();
        return $documentId;
    }
}
