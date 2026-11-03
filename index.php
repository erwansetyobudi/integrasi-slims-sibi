<?php
/**
 * @Created by          : Ari Nugraha (dicarve@gmail.com)
 * @Date                : 11/12/2025 00.00
 * @File name           : index.php
 */

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use SLiMS\DB;

global $dbs;

defined('INDEX_AUTH') OR die('Direct access not allowed!');

// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');
// start the session
require SB . 'admin/default/session.inc.php';
require SIMBIO . 'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO . 'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO . 'simbio_DB/datagrid/simbio_dbgrid.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');
$can_write = utility::havePrivilege('bibliography', 'w');

if (!$can_read) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

function httpQuery($query = [])
{
    return http_build_query(array_unique(array_merge($_GET, $query)));
}

function pluginQuery()
{
    $default_get = $_GET;
    $allowed_query = ['mod', 'id', '_']; 
    $default_get = array_filter($default_get, function ($key) use ($allowed_query) {
        return in_array($key, $allowed_query);
    }, ARRAY_FILTER_USE_KEY);

    return http_build_query(array_unique($default_get));
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/sibi_media.php';

// SIBI API base URI
$base_uri = 'https://api.buku.cloudapp.web.id/';
// API endpoints
$sibi_endpoint = [
    'Kurikulum Merdeka' => '/api/catalogue/getPenggerakTextBooks?limit=3000&type_pdf&order_by=updated_at',
    'Teks K-13' => '/api/catalogue/getTextBooks?limit=2000&type_pdf&order_by=updated_at',
    'Non-Teks' => '/api/catalogue/getNonTextBooks?limit=2000&type_pdf&order_by=updated_at&tag=Buku%20Model',
    'Buku STEM' => '/api/catalogue/getBooksByTag?Tag=STEM'
];
$book_type = [
    'buku_sekolah_penggerak' => 'Kurikulum Merdeka / Buku STEM',
    'buku_teks' => 'Teks K-13',
    'buku_non_teks' => 'Non-Teks'
];

$self_url = $_SERVER['PHP_SELF'].'?'.pluginQuery();
$action = $_GET['action'] ?? false;
$pdo = DB::getInstance();

// on print action
$records = [];
if ($action === 'harvest') {
    // utility::jsToastr(__('SIBI Harvesting'), __('Error in harvesting SIBI Data!'), 'error');
    // exit();

    // check type
    $type = $_GET['type'] ?? 'all';

    // utility::jsToastr(__('SIBI Harvesting'), __("Harvesting: $type"), 'success');
    // exit();

    $client = new Client(['base_uri' => $base_uri]);
    $stmt = $pdo->prepare(<<<SQL
        INSERT IGNORE INTO sibi_docs (
            id, title, slug, image, attachment, description, published_year, class, level, 
            writer, reviewer, translator, adapter, designer, cover_designer, ilustrator, 
            editor, aligner, publisher, contributor, language, context, subject, format, 
            isbn, curriculum, `collation`, type, edition, unit, status, category, 
            book_type, version, price_zone_1, price_zone_2, price_zone_3, price_zone_4, 
            price_zone_5A, price_zone_5B, created_at, updated_at, deleted_at
        ) VALUES (
            :id, :title, :slug, :image, :attachment, :description, :published_year, :class, :level, 
            :writer, :reviewer, :translator, :adapter, :designer, :cover_designer, :ilustrator, 
            :editor, :aligner, :publisher, :contributor, :language, :context, :subject, :format, 
            :isbn, :curriculum, :collation, :type, :edition, :unit, :status, :category, 
            :book_type, :version, :price_zone_1, :price_zone_2, :price_zone_3, :price_zone_4, 
            :price_zone_5A, :price_zone_5B, :created_at, :updated_at, :deleted_at
        )
    SQL);
    
    if ($type == 'all') {
        // Asycn non-blocking request to all endpoint
        $promises = [];
        foreach ($sibi_endpoint as $doctype => $api) :
            $promises[$doctype] = $client->getAsync($api);
        endforeach;
        $responses = Promise\Utils::settle($promises)->wait();

        foreach ($responses as $doctype => $response) :
            // var_dump($response);
            if ($response['state'] == 'fulfilled') {
                $body = $response['value']->getBody();
                $sibi_docs = json_decode($body, true);
                $results = $sibi_docs['results'];
                $rowsTotal = count($results);
                if ($rowsTotal > 0) {
                    $failed = 0;
                    $lastError = '';
                    // iterate the result and store to table
                    foreach ($results as $record) :
                        try {
                            // cleaning
                            if (isset($record['published_year']) && $record['published_year'] == '0000-00-00') {
                                $record['published_year'] = null;
                            }

                            $stmt->execute([
                                ':id'             => $record['id'],
                                ':title'          => $record['title'],
                                ':slug'           => $record['slug'],
                                ':image'          => $record['image'],
                                ':attachment'     => $record['attachment'],
                                ':description'    => $record['description'] ?? null,
                                ':published_year' => $record['published_year'] ?? null,
                                ':class'          => $record['class'] ?? null,
                                ':level'          => $record['level'] ?? null,
                                ':writer'         => $record['writer'] ?? null,
                                ':reviewer'       => $record['reviewer'] ?? null,
                                ':translator'     => $record['translator'] ?? null,
                                ':adapter'        => $record['adapter'] ?? null,
                                ':designer'       => $record['designer'] ?? null,
                                ':cover_designer' => $record['cover_designer'] ?? null,
                                ':ilustrator'     => $record['ilustrator'] ?? null,
                                ':editor'         => $record['editor'] ?? null,
                                ':aligner'        => $record['aligner'] ?? null,
                                ':publisher'      => $record['publisher'] ?? null,
                                ':contributor'    => $record['contributor'] ?? null,
                                ':language'       => $record['language'] ?? null,
                                ':context'        => $record['context'] ?? null,
                                ':subject'        => $record['subject'] ?? null,
                                ':format'         => $record['format'] ?? null,
                                ':isbn'           => $record['isbn'] ?? null,
                                ':curriculum'     => $record['curriculum'] ?? null,
                                ':collation'      => $record['collation'] ?? null,
                                ':type'           => $record['type'] ?? null,
                                ':edition'        => $record['edition'] ?? null,
                                ':unit'           => $record['unit'] ?? null,
                                ':status'         => $record['status'] ?? null,
                                ':category'       => $record['category'] ?? null,
                                ':book_type'      => $record['book_type'] ?? null,
                                ':version'        => $record['version'] ?? null,
                                ':price_zone_1'   => $record['price_zone_1'] ?? null,
                                ':price_zone_2'   => $record['price_zone_2'] ?? null,
                                ':price_zone_3'   => $record['price_zone_3'] ?? null,
                                ':price_zone_4'   => $record['price_zone_4'] ?? null,
                                ':price_zone_5A'  => $record['price_zone_5A'] ?? null,
                                ':price_zone_5B'  => $record['price_zone_5B'] ?? null,
                                ':created_at'     => $record['created_at'] ?? null,
                                ':updated_at'     => $record['updated_at'] ?? null,
                                ':deleted_at'     => $record['deleted_at'] ?? null
                            ]);

                        } catch (PDOException $e) {
                            $failed++;
                            $lastError = $e->getMessage();
                        }
                    endforeach;
                    if ($failed > 0) {
                        utility::jsToastr(__('SIBI Harvesting'), __("$failed documents failed to get from $doctype. Last error: $lastError"), 'warning');
                    }
                    $rowsStored = $rowsTotal-$failed;
                    utility::jsToastr(__('SIBI Harvesting'), __("Harvested $rowsStored (from total of $rowsTotal) documents from $doctype"), 'success');
                }
            } else {
                // var_dump($response);
                utility::jsToastr(__('SIBI Harvesting'), __("Request to $doctype FAILED with reason: ".$response['reason']->getMessage()), 'error');
            }

        endforeach;
    } else {
        // get records from selected endpoint
        
    }

    utility::jsToastr('SIBI', __('Harvesting finish'), 'success');
    echo '<script type="text/javascript">parent.jQuery(\'#mainContent\').simbioAJAX(\''.$self_url.'\');</script>';
    exit();
}

if ($action === 'clearcache') {
    $error = '';
    $affected_rows = 0;
    try {
        try {
            $affected_rows = $pdo->exec("TRUNCATE sibi_docs");
        } catch (PDOException $e) {
            // do nothing here
        }
        $affected_rows = $pdo->exec("DELETE FROM sibi_docs");
        utility::jsToastr('SIBI', "Cache cleared. $affected_rows record(s) deleted.", 'success');
    } catch (PDOException $e) {
        $error = $e->getMessage();
        utility::jsToastr('SIBI', "Cache clearing error caused by: $error", 'error');
    }
    
    echo '<script type="text/javascript">parent.jQuery(\'#mainContent\').simbioAJAX(\''.$self_url.'\');</script>';
    exit();
}

if ($action === 'syncbiblio') {
    $error = '';
    $inserted_rows = 0;
    try {
        // insert base biblio data
        $inserted_rows = $pdo->exec(<<<SQL
            INSERT INTO biblio (
                source, gmd_id, content_type_id, media_type_id, carrier_type_id, title, sor, image, notes, publish_year, classification, 
                isbn_issn, `collation`, edition, spec_detail_info, input_date, last_update, sibi_doc_id
            ) SELECT 'SIBI', 1, 20, 2, 16, title, writer, NULL, description, YEAR(published_year), class, 
                isbn, `collation`, edition, category, created_at, updated_at, id FROM sibi_docs
            ON DUPLICATE KEY UPDATE
                title=VALUES(title), sor=VALUES(sor), notes=VALUES(notes),
                publish_year=VALUES(publish_year), isbn_issn=VALUES(isbn_issn),
                last_update=NOW()
        SQL);

        // insert authors data
        $affected_rows = $pdo->exec(<<<SQL
            INSERT IGNORE INTO mst_author (author_name, input_date)
            WITH RECURSIVE author_values AS (
                -- Get the first value and the remainder of the string
                SELECT 
                    SUBSTRING_INDEX(sor, ',', 1) AS each_author,
                    SUBSTRING(sor, LOCATE(',', sor) + 1) AS remainder
                FROM biblio
                WHERE sor != ''

                UNION ALL

                -- Recursive step: Keep peeling off the next value until remainder is empty
                SELECT 
                    SUBSTRING_INDEX(remainder, ',', 1) AS each_author,
                    IF(LOCATE(',', remainder) > 0, SUBSTRING(remainder, LOCATE(',', remainder) + 1), '') AS remainder
                FROM author_values
                WHERE each_author != '' AND remainder != ''
            )
            SELECT TRIM(each_author), NOW() FROM author_values
        SQL);

        // insert biblio authors relation data
        $affected_rows = $pdo->exec(<<<SQL
            INSERT IGNORE INTO biblio_author (biblio_id, author_id)
            WITH RECURSIVE author_values AS (
                -- Get the first value and the remainder of the string
                SELECT
                    biblio_id,
                    SUBSTRING_INDEX(sor, ',', 1) AS each_author,
                    SUBSTRING(sor, LOCATE(',', sor) + 1) AS remainder
                FROM biblio
                WHERE sor != ''

                UNION ALL

                -- Recursive  peeling off the next value until remainder is empty
                SELECT 
                    biblio_id,
                    SUBSTRING_INDEX(remainder, ',', 1) AS each_author,
                    IF(LOCATE(',', remainder) > 0, SUBSTRING(remainder, LOCATE(',', remainder) + 1), '') AS remainder
                FROM author_values
                WHERE each_author != '' AND remainder != ''
            )
            SELECT av.biblio_id, a.author_id FROM author_values av
            JOIN mst_author a ON av.each_author=a.author_name
        SQL);

        // insert topic data
        $affected_rows = $pdo->exec(<<<SQL
            INSERT IGNORE INTO mst_topic (topic, topic_type, classification)
            WITH RECURSIVE sibi_topics AS (
                SELECT REPLACE(category, "_", " ") as topic_value FROM sibi_docs WHERE category != ''

                UNION ALL

                SELECT REPLACE(book_type, "_", " ") as topic_value FROM sibi_docs WHERE book_type != ''

                UNION ALL

                SELECT REPLACE(`subject`, "_", " ") as topic_value FROM sibi_docs WHERE `subject` != ''

                UNION ALL

                SELECT REPLACE(`level`, "_", " ") as topic_value FROM sibi_docs WHERE `level` != ''
            )
            SELECT st.topic_value, 't', 'SIBI' FROM sibi_topics st
        SQL);

        utility::jsToastr('SIBI', "$inserted_rows record(s) synchronized to SLiMS.", 'success');
    } catch (PDOException $e) {
        $error = $e->getMessage();
        utility::jsToastr('SIBI', "Sync error caused by: $error", 'error');
    }
    
    echo '<script type="text/javascript">parent.jQuery(\'#mainContent\').simbioAJAX(\''.$self_url.'\');</script>';
    exit();
}

// One small request per book: browser repeats this endpoint for a resumable batch.
if ($action === 'media_batch') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$can_write || $_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['sibi_media_token'] ?? '', $_POST['token'] ?? '')) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Akses ditolak / token tidak valid']); exit;
    }
    $id=(int)($_POST['sibi_doc_id'] ?? 0);
    try { $out=sibi_media_process($pdo,$id,(int)$_SESSION['uid']); echo json_encode(['ok'=>true,'id'=>$id,'result'=>$out]); }
    catch (Throwable $e) { echo json_encode(['ok'=>false,'id'=>$id,'error'=>$e->getMessage()]); }
    exit;
}
if ($action === 'media_ids') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$can_write || $_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['sibi_media_token'] ?? '', $_POST['token'] ?? '')) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Akses ditolak']); exit;
    }
    $ids=$pdo->query("SELECT s.id FROM sibi_docs s INNER JOIN biblio b ON b.sibi_doc_id=s.id WHERE (s.image IS NOT NULL AND s.image<>'') OR (s.attachment IS NOT NULL AND s.attachment<>'') ORDER BY s.id")->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['ok'=>true,'ids'=>array_map('intval',$ids)]); exit;
}
if ($action === 'downloadfile') {
    if (!$can_write) { http_response_code(403); exit; }
    try {
        $out=sibi_media_process($pdo,(int)($_GET['sibi_doc_id']??0),(int)$_SESSION['uid']);
        utility::jsToastr('SIBI','Cover dan PDF diproses: '.implode(', ',$out),'success');
    } catch (Throwable $e) { utility::jsToastr('SIBI',htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'),'error'); }
    echo '<script>parent.jQuery("#mainContent").simbioAJAX('.json_encode($self_url).');</script>'; exit;
}

// check number of docs in sibi_docs table
if (empty($_SESSION['sibi_media_token'])) $_SESSION['sibi_media_token']=bin2hex(random_bytes(24));
$sibi_records = 0;
$stmt = $pdo->prepare('SELECT COUNT(id) as total FROM sibi_docs');
$stmt->execute();
$sibi_records = $stmt->fetchColumn();

/* search form */
?>
<div class="menuBox">
    <div class="menuBoxInner">
        <div class="per_title">
            <h2><?php echo __('SIBI'); ?></h2>
        </div>
        <div class="sub_section">
            <div class="btn-group">
                <a target="blindSubmit" href="<?= $_SERVER['PHP_SELF'] . '?' . httpQuery(['action' => 'harvest']) ?>"
                   class="btn btn-success"><?php echo __('Harvest Metadata SIBI'); ?></a>
                <?php if ($sibi_records > 0) : ?>
                    <a target="blindSubmit" href="<?= $_SERVER['PHP_SELF'] . '?' . httpQuery(['action' => 'syncbiblio']) ?>"
                    class="btn btn-primary"><?php echo __('Sync Metadata SIBI ke SLiMS'); ?></a>
                    <?php if ($can_write): ?>
                    <button type="button" id="sibiBulkStart" class="btn btn-info">Unduh Cover + PDF Massal</button>
                    <button type="button" id="sibiBulkStop" class="btn btn-secondary" disabled>Hentikan</button>
                    <?php endif; ?>
                    <a target="blindSubmit" href="<?= $_SERVER['PHP_SELF'] . '?' . httpQuery(['action' => 'clearcache']) ?>"
                    class="btn btn-warning"><?php echo __('Bersihkan Cache SIBI'); ?></a>
                <?php endif; ?>
            </div>
            <form name="search" action="<?= $_SERVER['PHP_SELF'] . '?' . httpQuery() ?>" id="search" method="get"
                  class="form-inline"><?php echo __('Search'); ?>
                <input type="text" name="keywords" class="form-control col-md-3"/>
                <select name="type" class="form-control col-md-2">
                    <option value=""><?php echo __('Semua Jenis'); ?></option>
                    <?php foreach ($book_type as $type => $type_name) : ?>
                        <option value="<?=$type?>"><?php echo $type_name; ?> </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="s-btn btn btn-default"/>
            </form>
        </div>
        <div class="infoBox">Cari data SIBI yang sudah di-harvest. Sinkronkan bibliografi sebelum mengunduh media.</div>
        <div id="sibiBulkProgress" style="padding:10px;display:none;border:1px solid #ddd;margin-top:8px" aria-live="polite"></div>
        <?php
        if (!file_exists( REPOBS . 'sibi_docs' )) {
            echo '<div class="errorBox">Direktori <strong>sibi_docs</strong> untuk menyimpan file dokumen 
            belum ada di bawah direktori <strong>repository</strong>. Plugin akan mencoba membuatnya otomatis.
            Pastikan direktori repository mendapat hak akses tulis oleh SLiMS.
            </div>';
        }
        ?>
    </div>
</div>
<?php
/* search form end */
// table spec
$table_spec = 'sibi_docs';
// create datagrid
$datagrid = new simbio_datagrid();

function showImage($obj_db, $array_data)
{
    $img = $array_data[1];  
    $_output = '<div class="media w-80">
          <img class="rounded" style="width:80px" loading="lazy" src="'.$img.'" alt="cover image">
        </div>';

    return $_output;
}

function splitAuthors($obj_db, $array_data)
{
    $authors_str = trim($array_data[3]);
    $authors_arr = explode(',', $authors_str);
    $authors_urled = [];
    if (count($authors_arr) > 0) {
        foreach ($authors_arr as $author) :
            $authors_urled[] = '<a href="'.$_SERVER['PHP_SELF'].'?'.httpQuery(['keywords' => $author]).'">'.$author.'</a>';
        endforeach;
    }

    return implode(', ',$authors_urled);
}

function showDownload($obj_db, $array_data)
{
    $id = $array_data[0];
    $url = $array_data[6];
    $download_url = $_SERVER['PHP_SELF'] . '?' . httpQuery(['sibi_doc_id' => $id,'action' => 'downloadfile']);
    $_output = '<a target="blindSubmit" href="'. $download_url .'" class="btn btn-info">Unduh</a>';
    
    $pdfName = urldecode( basename($url) );
    $filePath = REPOBS . 'sibi_docs/' . $pdfName;

    if (file_exists($filePath)) {
        $_output .= ' <i class="fa fa-check"></i>';
    }

    return $_output;
}

$datagrid->setSQLColumn('sibi_docs.id AS \''.__('ID').'\'',
    'sibi_docs.image AS \''.__('Image').'\'',
    'sibi_docs.title AS \''.__('Title').'\'',
    'sibi_docs.writer AS \''.__('Authors').'\'',
    'sibi_docs.level AS \''.__('Level').'\'',
    'sibi_docs.isbn AS \''.__('ISBN').'\'',
    'sibi_docs.attachment AS \''.__('File').'\'',
    'sibi_docs.category'
);

$datagrid->modifyColumnContent(1, 'callback{showImage}');
$datagrid->modifyColumnContent(3, 'callback{splitAuthors}');
$datagrid->modifyColumnContent(6, 'callback{showDownload}');
$datagrid->setSQLorder('sibi_docs.level DESC');

// is there any search
$keywords = '';
$type = '';
$search_str = '';
if (isset($_GET['keywords']) && $_GET['keywords']) {
    $keywords = utility::filterData('keywords', 'get', true, true, true);
    $searchable_fields = array('title', 'writer', 'level', 'isbn');
    foreach ($searchable_fields as $search_field) :
        $search_str .= $search_field.' LIKE \''.$keywords.'%\' OR ';
    endforeach;
    $search_str = '('.substr_replace($search_str, '', -4).')';
}
if (isset($_GET['type']) && $_GET['type']) {
    $type = utility::filterData('type', 'get', true, true, true);
    if ($keywords) {
        $search_str .= " AND (category='$type')"; 
    } else {
        $search_str .= "(category='$type')";
    }
}
if ($search_str) {
    $datagrid->setSQLcriteria($search_str);
}

// set table and table header attributes
$datagrid->table_attr = 'id="dataList" class="s-table table"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
// set delete proccess URL
$datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, false);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
    echo '<div class="infoBox">'.$msg.' : '.htmlspecialchars($_GET['keywords']).'<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>'; //mfc
}
echo $datagrid_result;
/* main content end */

?><?php if ($can_write): ?>
<script>
(function(){
  var start=document.getElementById('sibiBulkStart'), stop=document.getElementById('sibiBulkStop'), panel=document.getElementById('sibiBulkProgress');
  if(!start||!stop||!panel)return;
  var stopped=false, token=<?= json_encode($_SESSION['sibi_media_token']) ?>;
  var base=<?= json_encode($_SERVER['PHP_SELF'].'?'.pluginQuery()) ?>;
  function endpoint(action){return base+'&action='+encodeURIComponent(action)}
  async function post(action, fields){
    var body=new URLSearchParams(Object.assign({token:token},fields||{}));
    var r=await fetch(endpoint(action),{method:'POST',body:body,credentials:'same-origin'});
    if(!r.ok)throw new Error('HTTP '+r.status);
    return await r.json();
  }
  stop.onclick=function(){stopped=true;stop.disabled=true;};
  start.onclick=async function(){
    if(!confirm('Unduh cover dan PDF untuk semua bibliografi SIBI yang telah disinkronkan? Proses dapat berlangsung lama dan menggunakan ruang disk.'))return;
    start.disabled=true;stop.disabled=false;stopped=false;panel.style.display='block';
    var success=0,failed=0, errors=[];
    try {
      var data=await post('media_ids'); if(!data.ok)throw new Error(data.error||'Tidak bisa membaca daftar');
      var ids=data.ids||[];
      for(var i=0;i<ids.length&&!stopped;i++){
        panel.textContent='Memproses '+(i+1)+' / '+ids.length+' | Berhasil: '+success+' | Gagal: '+failed;
        try {var out=await post('media_batch',{sibi_doc_id:ids[i]});
          if(out.ok)success++;else{failed++;errors.push('#'+ids[i]+': '+out.error);}
        } catch(e){failed++;errors.push('#'+ids[i]+': '+e.message);}
      }
      panel.textContent=(stopped?'Dihentikan. ':'Selesai. ')+'Berhasil: '+success+', gagal: '+failed+'. '+errors.slice(0,8).join(' | ');
    }catch(e){panel.textContent='Gagal: '+e.message;}
    start.disabled=false;stop.disabled=true;
  };
})();
</script>
<?php endif; ?>
