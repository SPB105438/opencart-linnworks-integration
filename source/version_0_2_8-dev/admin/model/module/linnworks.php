<?php
namespace Opencart\Admin\Model\Extension\Linnworks\Module;
class Linnworks extends \Opencart\System\Engine\Model{
 public function install():void{$q=[];$q[]="CREATE TABLE IF NOT EXISTS `".DB_PREFIX."linnworks_scan` (`scan_id` bigint NOT NULL AUTO_INCREMENT,`started_at` datetime NOT NULL,`completed_at` datetime NULL,`status` varchar(24) NOT NULL,`products_scanned` int NOT NULL DEFAULT 0,`ready_count` int NOT NULL DEFAULT 0,`warning_count` int NOT NULL DEFAULT 0,`critical_count` int NOT NULL DEFAULT 0,`health_score` decimal(5,2) NOT NULL DEFAULT 0,PRIMARY KEY(`scan_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";$q[]="CREATE TABLE IF NOT EXISTS `".DB_PREFIX."linnworks_scan_product` (`scan_product_id` bigint NOT NULL AUTO_INCREMENT,`scan_id` bigint NOT NULL,`product_id` int NOT NULL,`name` varchar(255) NOT NULL DEFAULT '',`model` varchar(255) NOT NULL DEFAULT '',`normalised_model` varchar(255) NOT NULL DEFAULT '',`ean` varchar(255) NOT NULL DEFAULT '',`price` decimal(15,4) NOT NULL DEFAULT 0,`quantity` int NOT NULL DEFAULT 0,`product_status` tinyint(1) NOT NULL DEFAULT 0,`readiness_score` int NOT NULL DEFAULT 0,`ready_for_sync` tinyint(1) NOT NULL DEFAULT 0,PRIMARY KEY(`scan_product_id`),UNIQUE KEY `scan_product`(`scan_id`,`product_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";$q[]="CREATE TABLE IF NOT EXISTS `".DB_PREFIX."linnworks_scan_issue` (`issue_id` bigint NOT NULL AUTO_INCREMENT,`scan_id` bigint NOT NULL,`product_id` int NULL,`severity` varchar(16) NOT NULL,`category` varchar(32) NOT NULL,`description` text NOT NULL,`recommendation` text NULL,`date_added` datetime NOT NULL,PRIMARY KEY(`issue_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";$q[]="CREATE TABLE IF NOT EXISTS `".DB_PREFIX."linnworks_discovery_run` (`discovery_run_id` bigint NOT NULL AUTO_INCREMENT,`started_at` datetime NOT NULL,`completed_at` datetime NULL,`status` varchar(24) NOT NULL,`pages_processed` int NOT NULL DEFAULT 0,`items_received` int NOT NULL DEFAULT 0,`items_inserted` int NOT NULL DEFAULT 0,`items_updated` int NOT NULL DEFAULT 0,`error_count` int NOT NULL DEFAULT 0,`error_message` text NULL,PRIMARY KEY(`discovery_run_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";$q[]="CREATE TABLE IF NOT EXISTS `".DB_PREFIX."linnworks_discovery_item` (`discovery_item_id` bigint NOT NULL AUTO_INCREMENT,`discovery_run_id` bigint NOT NULL,`stock_item_id` varchar(36) NOT NULL,`stock_item_int_id` int NULL,`sku` varchar(255) NOT NULL DEFAULT '',`item_title` varchar(255) NOT NULL DEFAULT '',`barcode` varchar(255) NOT NULL DEFAULT '',`purchase_price` decimal(15,4) NULL,`retail_price` decimal(15,4) NULL,`quantity` int NULL,`available_quantity` int NULL,`is_composite_parent` tinyint(1) NOT NULL DEFAULT 0,`is_variation_parent` tinyint(1) NOT NULL DEFAULT 0,`linnworks_last_update` datetime NULL,`payload_hash` char(64) NOT NULL DEFAULT '',`date_discovered` datetime NOT NULL,`date_modified` datetime NOT NULL,PRIMARY KEY(`discovery_item_id`),UNIQUE KEY `stock_item_id`(`stock_item_id`),KEY `sku`(`sku`),KEY `barcode`(`barcode`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";$q[]="CREATE TABLE IF NOT EXISTS `".DB_PREFIX."linnworks_mapping_run` (`mapping_run_id` bigint NOT NULL AUTO_INCREMENT,`scan_id` bigint NOT NULL,`started_at` datetime NOT NULL,`completed_at` datetime NULL,`status` varchar(24) NOT NULL,`products_processed` int NOT NULL DEFAULT 0,`suggested_count` int NOT NULL DEFAULT 0,`conflict_count` int NOT NULL DEFAULT 0,`unmatched_count` int NOT NULL DEFAULT 0,`error_message` text NULL,PRIMARY KEY(`mapping_run_id`),KEY `status`(`status`),KEY `scan_id`(`scan_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$q[]="CREATE TABLE IF NOT EXISTS `".DB_PREFIX."linnworks_mapping_review` (`mapping_review_id` bigint NOT NULL AUTO_INCREMENT,`mapping_run_id` bigint NOT NULL,`product_id` int NOT NULL,`stock_item_id` varchar(36) NOT NULL DEFAULT '',`opencart_name` varchar(255) NOT NULL DEFAULT '',`opencart_ean` varchar(255) NOT NULL DEFAULT '',`opencart_model` varchar(255) NOT NULL DEFAULT '',`linnworks_title` varchar(255) NOT NULL DEFAULT '',`linnworks_sku` varchar(255) NOT NULL DEFAULT '',`linnworks_barcode` varchar(255) NOT NULL DEFAULT '',`match_method` varchar(24) NOT NULL DEFAULT '',`confidence` int NOT NULL DEFAULT 0,`status` varchar(24) NOT NULL DEFAULT 'suggested',`conflict_reason` text NULL,`approved_by` varchar(255) NOT NULL DEFAULT '',`approved_date` datetime NULL,`date_added` datetime NOT NULL,`date_modified` datetime NOT NULL,PRIMARY KEY(`mapping_review_id`),KEY `mapping_run_id`(`mapping_run_id`),KEY `product_id`(`product_id`),KEY `stock_item_id`(`stock_item_id`),KEY `opencart_ean`(`opencart_ean`),KEY `linnworks_barcode`(`linnworks_barcode`),KEY `status`(`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
foreach($q as $s)$this->db->query($s);$this->migrate('linnworks_scan_product','ean',"varchar(255) NOT NULL DEFAULT '' AFTER `normalised_model`",'identifier_value');}
 private function migrate(string $table,string $column,string $definition,string $old=''):void{$rows=$this->db->query("SHOW COLUMNS FROM `".DB_PREFIX.$table."`")->rows;$names=array_column($rows,'Field');if(!in_array($column,$names,true)){$this->db->query("ALTER TABLE `".DB_PREFIX.$table."` ADD `".$column."` ".$definition);if($old!==''&&in_array($old,$names,true))$this->db->query("UPDATE `".DB_PREFIX.$table."` SET `".$column."`=`".$old."` WHERE `".$column."`=''");}}
 public function authorize(array $s):array{return $this->request('POST',$s['auth_url'],['ApplicationId'=>trim($s['application_id']),'ApplicationSecret'=>trim($s['application_secret']),'Token'=>trim($s['token'])],'',(int)$s['timeout']);}
 public function locations(array $s):array{$a=$this->authorize($s);return $this->request('GET',rtrim($a['Server'],'/').'/api/Inventory/GetStockLocations',null,$a['Token'],(int)$s['timeout']);}
 public function discoverProducts(array $s,array $o):array{$this->install();$this->db->query("INSERT INTO `".DB_PREFIX."linnworks_discovery_run` SET started_at=NOW(),status='running'");$run=(int)$this->db->getLastId();try{$a=$this->authorize($s);$size=max(1,min(200,(int)$o['page_size']));$max=max(1,min(500,(int)$o['max_pages']));$page=1;$received=$inserted=$updated=0;do{$payload=['keyword'=>'','loadCompositeParents'=>(bool)$o['composite'],'loadVariationParents'=>(bool)$o['variations'],'entriesPerPage'=>$size,'pageNumber'=>$page,'dataRequirements'=>$o['stock_levels']?['StockLevels']:[],'searchTypes'=>[]];$items=$this->request('POST',rtrim($a['Server'],'/').'/api/Stock/GetStockItemsFull',$payload,$a['Token'],(int)$s['timeout']);if(isset($items['Items'])&&is_array($items['Items']))$items=$items['Items'];if(!is_array($items))throw new \RuntimeException('Unrecognised Linnworks product response.');foreach($items as $x){if(!is_array($x))continue;$id=(string)($x['StockItemId']??'');if($id==='')continue;$levels=$x['StockLevels']??[];$qty=$avail=0;$last=null;foreach($levels as $l){$qty+=(int)($l['StockLevel']??0);$avail+=(int)($l['Available']??0);if(!empty($l['LastUpdateDate']))$last=$l['LastUpdateDate'];}$hash=hash('sha256',json_encode($x));$old=$this->db->query("SELECT payload_hash FROM `".DB_PREFIX."linnworks_discovery_item` WHERE stock_item_id='".$this->db->escape($id)."'");$title=(string)($x['ItemTitle']??$x['Title']??$x['ItemDescription']??'');$barcode=(string)($x['BarcodeNumber']??$x['Barcode']??'');$sql="discovery_run_id=$run,stock_item_int_id=".(int)($x['StockItemIntId']??0).",sku='".$this->db->escape((string)($x['SKU']??$x['ItemNumber']??''))."',item_title='".$this->db->escape($title)."',barcode='".$this->db->escape($barcode)."',purchase_price=".(float)($x['PurchasePrice']??$x['StockItemPurchasePrice']??0).",retail_price=".(float)($x['RetailPrice']??0).",quantity=$qty,available_quantity=$avail,is_composite_parent=".(int)($x['IsCompositeParent']??0).",is_variation_parent=".(int)($x['IsVariationParent']??0).",payload_hash='$hash',date_discovered=NOW(),date_modified=NOW()";if($old->num_rows){if($old->row['payload_hash']!==$hash){$this->db->query("UPDATE `".DB_PREFIX."linnworks_discovery_item` SET $sql WHERE stock_item_id='".$this->db->escape($id)."'");$updated++;}}else{$this->db->query("INSERT INTO `".DB_PREFIX."linnworks_discovery_item` SET stock_item_id='".$this->db->escape($id)."',$sql");$inserted++;}$received++;}$count=count($items);$page++;}while($count===$size&&$page<=$max);$pages=$page-1;$this->db->query("UPDATE `".DB_PREFIX."linnworks_discovery_run` SET completed_at=NOW(),status='completed',pages_processed=$pages,items_received=$received,items_inserted=$inserted,items_updated=$updated WHERE discovery_run_id=$run");return $this->discoverySummary($run);}catch(\Throwable $e){$this->db->query("UPDATE `".DB_PREFIX."linnworks_discovery_run` SET completed_at=NOW(),status='failed',error_count=1,error_message='".$this->db->escape($e->getMessage())."' WHERE discovery_run_id=$run");throw $e;}}
 public function discoverySummary(int $id=0):array{$this->install();$w=$id?' WHERE discovery_run_id='.(int)$id:'';$q=$this->db->query("SELECT * FROM `".DB_PREFIX."linnworks_discovery_run`$w ORDER BY discovery_run_id DESC LIMIT 1");if(!$q->num_rows)return [];$r=$q->row;$r['cached_items']=(int)$this->db->query("SELECT COUNT(*) total FROM `".DB_PREFIX."linnworks_discovery_item`")->row['total'];return $r;}
 public function discoveryRuns(int $limit=100):array{$this->install();return $this->db->query("SELECT * FROM `".DB_PREFIX."linnworks_discovery_run` ORDER BY discovery_run_id DESC LIMIT ".(int)$limit)->rows;}
 private function discoveryWhere(array $f):string{$w=['1=1'];if(!empty($f['search'])){$s=$this->db->escape($f['search']);$w[]="(sku LIKE '%$s%' OR item_title LIKE '%$s%' OR barcode LIKE '%$s%' OR stock_item_id LIKE '%$s%')";}if(($f['barcode']??'')==='missing')$w[]="barcode=''";if(($f['barcode']??'')==='present')$w[]="barcode<>''";if(($f['stock']??'')==='in')$w[]='available_quantity>0';if(($f['stock']??'')==='out')$w[]='available_quantity<=0';return implode(' AND ',$w);}
 public function discoveredProducts(array $f):array{$this->install();$limit=max(1,min(100000,(int)($f['limit']??50)));$offset=(max(1,(int)($f['page']??1))-1)*$limit;return $this->db->query("SELECT * FROM `".DB_PREFIX."linnworks_discovery_item` WHERE ".$this->discoveryWhere($f)." ORDER BY sku LIMIT $offset,$limit")->rows;}
 public function discoveredProductsTotal(array $f):int{return (int)$this->db->query("SELECT COUNT(*) total FROM `".DB_PREFIX."linnworks_discovery_item` WHERE ".$this->discoveryWhere($f))->row['total'];}
 public function scan():array{$this->install();$this->db->query("INSERT INTO `".DB_PREFIX."linnworks_scan` SET started_at=NOW(),status='running'");$id=(int)$this->db->getLastId();$lang=(int)$this->config->get('config_language_id');$q=$this->db->query("SELECT p.product_id,p.model,p.price,p.quantity,p.status,COALESCE(pd.name,'') name,COALESCE(MAX(CASE WHEN UPPER(pc.code)='EAN' THEN TRIM(pc.value) END),'') ean FROM `".DB_PREFIX."product` p LEFT JOIN `".DB_PREFIX."product_description` pd ON pd.product_id=p.product_id AND pd.language_id=$lang LEFT JOIN `".DB_PREFIX."product_code` pc ON pc.product_id=p.product_id GROUP BY p.product_id,p.model,p.price,p.quantity,p.status,pd.name");$ready=$warn=$crit=0;foreach($q->rows as $p){$ean=trim($p['ean']);$issues=[];if($ean==='')$issues[]=['warning','ean','Missing EAN','Add an EAN.'];if((float)$p['price']<0)$issues[]=['critical','price','Negative price','Correct the price.'];if((int)$p['quantity']<0)$issues[]=['critical','stock','Negative stock','Correct the quantity.'];$score=100;$blocked=false;foreach($issues as $i){if($i[0]==='critical'){$crit++;$score-=35;$blocked=true;}else{$warn++;$score-=10;}$this->db->query("INSERT INTO `".DB_PREFIX."linnworks_scan_issue` SET scan_id=$id,product_id=".(int)$p['product_id'].",severity='".$i[0]."',category='".$i[1]."',description='".$this->db->escape($i[2])."',recommendation='".$this->db->escape($i[3])."',date_added=NOW()");}$ok=$ean!==''&&!$blocked;if($ok)$ready++;$this->db->query("INSERT INTO `".DB_PREFIX."linnworks_scan_product` SET scan_id=$id,product_id=".(int)$p['product_id'].",name='".$this->db->escape($p['name'])."',model='".$this->db->escape($p['model'])."',normalised_model='".$this->db->escape(strtoupper(str_replace([' ','-','_'],'',$p['model'])))."',ean='".$this->db->escape($ean)."',price=".(float)$p['price'].",quantity=".(int)$p['quantity'].",product_status=".(int)$p['status'].",readiness_score=".max(0,$score).",ready_for_sync=".(int)$ok);}$total=count($q->rows);$health=$total?round($ready/$total*100,2):0;$this->db->query("UPDATE `".DB_PREFIX."linnworks_scan` SET completed_at=NOW(),status='completed',products_scanned=$total,ready_count=$ready,warning_count=$warn,critical_count=$crit,health_score=$health WHERE scan_id=$id");return $this->scanSummary($id);}
 public function scanSummary(int $id=0):array{$this->install();$w=$id?' WHERE scan_id='.(int)$id:'';$q=$this->db->query("SELECT * FROM `".DB_PREFIX."linnworks_scan`$w ORDER BY scan_id DESC LIMIT 1");if(!$q->num_rows)return [];$r=$q->row;$c=$this->db->query("SELECT COUNT(*) total,SUM(ean<>'') covered FROM `".DB_PREFIX."linnworks_scan_product` WHERE scan_id=".(int)$r['scan_id'])->row;$r['ean_coverage']=$c['total']?round($c['covered']/$c['total']*100,1):0;return $r;}
 public function scanHistory(int $limit):array{return $this->db->query("SELECT * FROM `".DB_PREFIX."linnworks_scan` ORDER BY scan_id DESC LIMIT ".(int)$limit)->rows;}public function scanProducts(array $f):array{$id=(int)($f['scan_id']??0);if(!$id)$id=(int)($this->scanSummary()['scan_id']??0);return $this->db->query("SELECT * FROM `".DB_PREFIX."linnworks_scan_product` WHERE scan_id=$id ORDER BY ready_for_sync,product_id LIMIT ".(int)($f['limit']??250))->rows;}public function scanIssues(array $f):array{$id=(int)($f['scan_id']??0);if(!$id)$id=(int)($this->scanSummary()['scan_id']??0);return $this->db->query("SELECT i.*,p.name FROM `".DB_PREFIX."linnworks_scan_issue` i LEFT JOIN `".DB_PREFIX."linnworks_scan_product` p ON p.scan_id=i.scan_id AND p.product_id=i.product_id WHERE i.scan_id=$id")->rows;}
 private function request(string $method,string $url,?array $payload,string $token,int $timeout):array{if(!function_exists('curl_init'))throw new \RuntimeException('PHP cURL is not installed.');$ch=curl_init($url);$h=['Accept: application/json','Content-Type: application/json'];if($token)$h[]='Authorization: '.$token;$o=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$h,CURLOPT_CONNECTTIMEOUT=>$timeout,CURLOPT_TIMEOUT=>$timeout,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];if($method==='POST'){$o[CURLOPT_POST]=true;$o[CURLOPT_POSTFIELDS]=json_encode($payload??[]);}curl_setopt_array($ch,$o);$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);if($body===false||$err)throw new \RuntimeException('HTTPS request failed: '.$err);$j=json_decode($body,true);if($code<200||$code>=300)throw new \RuntimeException('Linnworks HTTP '.$code.': '.substr((string)($j['Message']??$j['message']??$body),0,500));if(!is_array($j))throw new \RuntimeException('Linnworks returned invalid JSON.');return $j;}

 public function generateMappingSuggestions(): array {
    $this->install();

    $latest_scan = $this->db->query(
        "SELECT scan_id FROM `" . DB_PREFIX . "linnworks_scan` " .
        "WHERE status = 'completed' ORDER BY scan_id DESC LIMIT 1"
    );

    if (!$latest_scan->num_rows) {
        throw new \RuntimeException('Run the OpenCart Catalogue Scanner before generating mapping suggestions.');
    }

    $discovery_total = (int)$this->db->query(
        "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "linnworks_discovery_item`"
    )->row['total'];

    if ($discovery_total === 0) {
        throw new \RuntimeException('Run Linnworks Product Discovery before generating mapping suggestions.');
    }

    $scan_id = (int)$latest_scan->row['scan_id'];

    $this->db->query(
        "INSERT INTO `" . DB_PREFIX . "linnworks_mapping_run` SET " .
        "scan_id = " . $scan_id . ", " .
        "started_at = NOW(), status = 'running'"
    );

    $mapping_run_id = (int)$this->db->getLastId();

    try {
        // Preserve approvals/rejections from previous mapping runs. Only replace
        // unapproved suggestions and conflicts for the current generation pass.
        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "linnworks_mapping_review` " .
            "WHERE status IN ('suggested', 'conflict')"
        );

        $products = $this->db->query(
            "SELECT product_id, name, model, normalised_model, TRIM(ean) AS ean " .
            "FROM `" . DB_PREFIX . "linnworks_scan_product` " .
            "WHERE scan_id = " . $scan_id . " ORDER BY product_id"
        )->rows;

        $processed = 0;
        $suggested = 0;
        $conflicts = 0;
        $unmatched = 0;

        foreach ($products as $product) {
            $processed++;
            $ean = trim((string)$product['ean']);

            if ($ean === '') {
                $unmatched++;
                continue;
            }

            // EANs/barcodes are treated as strings. Never cast to integer,
            // because a valid value may contain leading zeroes.
            $matches = $this->db->query(
                "SELECT stock_item_id, sku, item_title, barcode " .
                "FROM `" . DB_PREFIX . "linnworks_discovery_item` " .
                "WHERE TRIM(barcode) = '" . $this->db->escape($ean) . "' " .
                "ORDER BY stock_item_id"
            )->rows;

            if (count($matches) === 1) {
                $match = $matches[0];

                $this->insertMappingReview([
                    'mapping_run_id'    => $mapping_run_id,
                    'product_id'        => (int)$product['product_id'],
                    'stock_item_id'     => (string)$match['stock_item_id'],
                    'opencart_name'     => (string)$product['name'],
                    'opencart_ean'      => $ean,
                    'opencart_model'    => (string)$product['model'],
                    'linnworks_title'   => (string)$match['item_title'],
                    'linnworks_sku'     => (string)$match['sku'],
                    'linnworks_barcode' => (string)$match['barcode'],
                    'match_method'      => 'ean_exact',
                    'confidence'        => 100,
                    'status'            => 'suggested',
                    'conflict_reason'   => ''
                ]);

                $suggested++;
                continue;
            }

            if (count($matches) > 1) {
                foreach ($matches as $match) {
                    $this->insertMappingReview([
                        'mapping_run_id'    => $mapping_run_id,
                        'product_id'        => (int)$product['product_id'],
                        'stock_item_id'     => (string)$match['stock_item_id'],
                        'opencart_name'     => (string)$product['name'],
                        'opencart_ean'      => $ean,
                        'opencart_model'    => (string)$product['model'],
                        'linnworks_title'   => (string)$match['item_title'],
                        'linnworks_sku'     => (string)$match['sku'],
                        'linnworks_barcode' => (string)$match['barcode'],
                        'match_method'      => 'ean_exact',
                        'confidence'        => 0,
                        'status'            => 'conflict',
                        'conflict_reason'   => 'The OpenCart EAN matches multiple Linnworks items.'
                    ]);
                }

                $conflicts++;
                continue;
            }

            $unmatched++;
        }

        $this->db->query(
            "UPDATE `" . DB_PREFIX . "linnworks_mapping_run` SET " .
            "completed_at = NOW(), status = 'completed', " .
            "products_processed = " . $processed . ", " .
            "suggested_count = " . $suggested . ", " .
            "conflict_count = " . $conflicts . ", " .
            "unmatched_count = " . $unmatched . " " .
            "WHERE mapping_run_id = " . $mapping_run_id
        );

        return $this->mappingSummary($mapping_run_id);
    } catch (\Throwable $e) {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "linnworks_mapping_run` SET " .
            "completed_at = NOW(), status = 'failed', error_message = '" .
            $this->db->escape($e->getMessage()) . "' " .
            "WHERE mapping_run_id = " . $mapping_run_id
        );

        throw $e;
    }
}

private function insertMappingReview(array $mapping): void {
    $this->db->query(
        "INSERT INTO `" . DB_PREFIX . "linnworks_mapping_review` SET " .
        "mapping_run_id = " . (int)$mapping['mapping_run_id'] . ", " .
        "product_id = " . (int)$mapping['product_id'] . ", " .
        "stock_item_id = '" . $this->db->escape($mapping['stock_item_id']) . "', " .
        "opencart_name = '" . $this->db->escape($mapping['opencart_name']) . "', " .
        "opencart_ean = '" . $this->db->escape($mapping['opencart_ean']) . "', " .
        "opencart_model = '" . $this->db->escape($mapping['opencart_model']) . "', " .
        "linnworks_title = '" . $this->db->escape($mapping['linnworks_title']) . "', " .
        "linnworks_sku = '" . $this->db->escape($mapping['linnworks_sku']) . "', " .
        "linnworks_barcode = '" . $this->db->escape($mapping['linnworks_barcode']) . "', " .
        "match_method = '" . $this->db->escape($mapping['match_method']) . "', " .
        "confidence = " . (int)$mapping['confidence'] . ", " .
        "status = '" . $this->db->escape($mapping['status']) . "', " .
        "conflict_reason = '" . $this->db->escape($mapping['conflict_reason']) . "', " .
        "date_added = NOW(), date_modified = NOW()"
    );
}

public function mappingSummary(int $mapping_run_id = 0): array {
    $this->install();

    $where = $mapping_run_id
        ? ' WHERE mapping_run_id = ' . (int)$mapping_run_id
        : '';

    $query = $this->db->query(
        "SELECT * FROM `" . DB_PREFIX . "linnworks_mapping_run`" .
        $where . " ORDER BY mapping_run_id DESC LIMIT 1"
    );

    return $query->num_rows ? $query->row : [];
}

public function mappingRuns(int $limit = 100): array {
    $this->install();

    return $this->db->query(
        "SELECT * FROM `" . DB_PREFIX . "linnworks_mapping_run` " .
        "ORDER BY mapping_run_id DESC LIMIT " . max(1, (int)$limit)
    )->rows;
}

private function mappingWhere(array $filter): string {
    $where = ['1 = 1'];

    if (!empty($filter['search'])) {
        $search = $this->db->escape(trim((string)$filter['search']));
        $where[] = "(opencart_name LIKE '%" . $search . "%' " .
            "OR opencart_ean LIKE '%" . $search . "%' " .
            "OR opencart_model LIKE '%" . $search . "%' " .
            "OR linnworks_title LIKE '%" . $search . "%' " .
            "OR linnworks_sku LIKE '%" . $search . "%' " .
            "OR linnworks_barcode LIKE '%" . $search . "%')";
    }

    if (!empty($filter['status'])) {
        $where[] = "status = '" . $this->db->escape($filter['status']) . "'";
    }

    return implode(' AND ', $where);
}

public function mappingReviews(array $filter = []): array {
    $this->install();

    $limit = max(1, min(200, (int)($filter['limit'] ?? 50)));
    $page = max(1, (int)($filter['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    return $this->db->query(
        "SELECT * FROM `" . DB_PREFIX . "linnworks_mapping_review` " .
        "WHERE " . $this->mappingWhere($filter) . " " .
        "ORDER BY FIELD(status, 'conflict', 'suggested', 'approved', 'rejected'), " .
        "opencart_name, mapping_review_id LIMIT " . $offset . "," . $limit
    )->rows;
}

public function mappingReviewTotal(array $filter = []): int {
    $this->install();

    return (int)$this->db->query(
        "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "linnworks_mapping_review` " .
        "WHERE " . $this->mappingWhere($filter)
    )->row['total'];
}
}
