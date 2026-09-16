<?php

declare(strict_types=1);
namespace App\Services;
use App\Models\OrderRepository;
use InvalidArgumentException;

final class OrderDocumentService
{
    private $repo;
    public function __construct(OrderRepository $repo) { $this->repo=$repo; }
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
            $result[]=['name'=>substr($name,0,300),'quantity'=>$quantity,'unit_cents'=>$unit,'vat'=>$vat,'net_cents'=>$n,'tax_cents'=>$t,'gross_cents'=>$g];
            $net+=$n; $tax+=$t; $gross+=$g;
        }
        return ['items'=>$result,'net_cents'=>$net,'tax_cents'=>$tax,'gross_cents'=>$gross];
    }
    public function issue(int $orderId,array $input,string $actor): int
    {
        $db=$this->repo->db();
        $key=(string)($input['request_key']??'');
        if (!preg_match('/^[a-f0-9]{32,80}$/D',$key)) { throw new InvalidArgumentException('Odśwież formularz dokumentu.'); }
        return $db->transaction(function () use ($orderId,$input,$actor,$db,$key) {
            $existing=$db->fetch('SELECT id,order_id FROM om_documents WHERE request_key=:k',['k'=>$key]);
            if ($existing) {
                if ((int)$existing['order_id']!==$orderId) { throw new InvalidArgumentException('Nieprawidłowy klucz dokumentu.'); }
                return (int)$existing['id'];
            }
            $lock=$db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite' ? '' : ' FOR UPDATE';
            $series=$db->fetch('SELECT * FROM om_series WHERE id=:id'.$lock,['id'=>(int)($input['series_id']??0)]);
            if (!$series) { throw new InvalidArgumentException('Wybierz serię numeracji.'); }
            $order=$this->repo->order($orderId);
            $seller=$this->repo->setting('seller');
            if (empty($seller['name']) || empty($seller['address']) || empty($seller['nip'])) { throw new InvalidArgumentException('Najpierw uzupełnij dane sprzedawcy w ustawieniach dokumentów.'); }
            $buyer=trim((string)($input['buyer']??''));
            if ($buyer==='') { throw new InvalidArgumentException('Uzupełnij dane nabywcy.'); }
            $snapshot=self::calculate($input['items']??[]);
            $parentId=null; $parent=null;
            $correction=in_array($series['kind'],['invoice_correction','receipt_correction'],true);
            if ($correction) {
                $parentId=(int)($input['parent_id']??0);
                $parent=$db->fetch('SELECT * FROM om_documents WHERE id=:id AND order_id=:o'.$lock,['id'=>$parentId,'o'=>$orderId]);
                $expected=$series['kind']==='invoice_correction'?'invoice':'receipt';
                if (!$parent || $parent['kind']!==$expected || trim((string)($input['reason']??''))==='') { throw new InvalidArgumentException('Wybierz dokument pierwotny właściwego typu i podaj powód korekty.'); }
                $previous=$db->fetch('SELECT snapshot_json FROM om_documents WHERE parent_id=:id ORDER BY id DESC LIMIT 1',['id'=>$parentId]);
                $before=json_decode($previous['snapshot_json']??$parent['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
                $snapshot['before']=['items'=>$before['items'],'net_cents'=>$before['net_cents'],'tax_cents'=>$before['tax_cents'],'gross_cents'=>$before['gross_cents'],'buyer'=>$before['buyer']];
                $snapshot['difference_cents']=$snapshot['gross_cents']-$before['gross_cents'];
                $snapshot['parent_number']=$parent['number'];
            } elseif ($snapshot['gross_cents']!==(int)$order['total_cents']) {
                throw new InvalidArgumentException('Suma dokumentu musi zgadzać się z zamówieniem (uwzględnij dostawę i rabaty).');
            }
            $now=new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw'));
            $numbering=json_decode((string)($series['numbering_json']??''),true)?:[];
            $period=($numbering['format']??'')==='MONTHLY'?$now->format('Y-m'):$now->format('Y');
            $counter=(int)$series['next_number'];
            if (!empty($numbering['reset']) && !empty($series['numbering_period']) && $series['numbering_period']!==$period) { $counter=(int)$numbering['start']; }
            $digits=(string)$counter;
            if ($numbering) { $digits=str_pad($digits,(int)($numbering['length']??0),'0',STR_PAD_LEFT); }
            $number=strtr($series['pattern'],['{N}'=>$digits,'{YYYY}'=>$now->format('Y'),'{MM}'=>$now->format('m')]);
            $snapshot+=['seller'=>$seller,'buyer'=>$buyer,'currency'=>$order['currency'],'sale_date'=>substr($order['ordered_at'],0,10),'issue_date'=>$now->format('Y-m-d'),'reason'=>trim((string)($input['reason']??'')),'order_number'=>$order['external_id'],'settlement_state'=>'local','fiscalized'=>false,'series_notes'=>(string)($numbering['notes']??'')];
            $id=(int)$db->insert('om_documents',['order_id'=>$orderId,'series_id'=>$series['id'],'kind'=>$series['kind'],'number'=>$number,'parent_id'=>$parentId,'request_key'=>$key,'snapshot_json'=>OrderRepository::json($snapshot),'created_at'=>gmdate('Y-m-d H:i:s')]);
            $db->update('om_series',['next_number'=>$counter+1,'numbering_period'=>$numbering?$period:null],'id=:id',['id'=>$series['id']]);
            $this->repo->event($orderId,'Zapisano dokument lokalny '.$number,$actor);
            return $id;
        });
    }
}
