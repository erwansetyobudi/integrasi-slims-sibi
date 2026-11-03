<?php
/** Media importer for SIBI; loaded only by authenticated plugin index. */
function sibi_media_download($url, $destination, $kind) {
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) throw new RuntimeException('URL tidak valid');
    $parts = parse_url($url);
    if (strtolower($parts['scheme'] ?? '') !== 'https') throw new RuntimeException('Hanya HTTPS diizinkan');
    $host = strtolower($parts['host'] ?? '');
    if (!in_array($host, ['static-sc.cloudapp.web.id','static.sc.cloudapp.web.id'], true)) throw new RuntimeException('Host media tidak diizinkan: '.$host);
    $dir = dirname($destination);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Tidak dapat membuat direktori '.$dir);
    $tmp = $destination.'.part.'.bin2hex(random_bytes(4));
    try {
        $client = new \GuzzleHttp\Client(['timeout'=>120, 'connect_timeout'=>15, 'allow_redirects'=>false, 'http_errors'=>true]);
        $response = $client->request('GET', $url, ['sink'=>$tmp, 'verify'=>true]);
        $size = filesize($tmp);
        if ($size === false || $size < 100) throw new RuntimeException('Berkas kosong atau terlalu kecil');
        $fp = fopen($tmp, 'rb'); $header = fread($fp, 16); fclose($fp);
        if ($kind === 'pdf') {
            if (substr($header,0,5) !== '%PDF-') throw new RuntimeException('Respons bukan PDF');
        } else {
            $info = @getimagesize($tmp);
            if (!$info || !in_array($info['mime'], ['image/jpeg','image/png','image/webp','image/gif'], true)) throw new RuntimeException('Respons bukan gambar yang didukung');
        }
        if (!rename($tmp, $destination)) throw new RuntimeException('Gagal memindahkan berkas');
        return $size;
    } finally { if (is_file($tmp)) @unlink($tmp); }
}
function sibi_media_process($pdo, $id, $uid) {
    $q=$pdo->prepare('SELECT s.id,s.title,s.image,s.attachment,b.biblio_id,b.image AS local_image FROM sibi_docs s LEFT JOIN biblio b ON b.sibi_doc_id=s.id WHERE s.id=?');
    $q->execute([$id]); $r=$q->fetch(PDO::FETCH_ASSOC);
    if (!$r) throw new RuntimeException('Metadata tidak ditemukan');
    if (empty($r['biblio_id'])) throw new RuntimeException('Belum sinkron ke bibliografi; klik Sync Metadata terlebih dahulu');
    $result=['cover'=>'skip','pdf'=>'skip'];
    $bid=(int)$r['biblio_id'];
    if (!empty($r['image'])) {
        $ext = strtolower(pathinfo(parse_url($r['image'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'],true)) $ext='jpg';
        $name='sibi_'.$r['id'].'.'.$ext;
        $path=IMGBS.'docs/'.$name;
        if (!is_file($path) || filesize($path)<100) { sibi_media_download($r['image'],$path,'image'); $result['cover']='downloaded'; }
        else $result['cover']='existing';
        $st=$pdo->prepare('UPDATE biblio SET image=?,last_update=NOW() WHERE biblio_id=? AND (image IS NULL OR image="" OR image=? OR image LIKE "https://%")');
        $st->execute([$name,$bid,$name]);
        // search_biblio is refreshed by SLiMS Biblio Indexes; best-effort update for existing index.
        try { $st=$pdo->prepare('UPDATE search_biblio SET image=? WHERE biblio_id=?'); $st->execute([$name,$bid]); } catch (Throwable $ignored) {}
    }
    if (!empty($r['attachment'])) {
        $name='sibi_'.$r['id'].'.pdf';
        $path=REPOBS.'sibi_docs/'.$name;
        if (!is_file($path) || filesize($path)<100) { sibi_media_download($r['attachment'],$path,'pdf'); $result['pdf']='downloaded'; }
        else $result['pdf']='existing';
        $find=$pdo->prepare('SELECT f.file_id FROM files f INNER JOIN biblio_attachment ba ON ba.file_id=f.file_id WHERE ba.biblio_id=? AND f.file_name=? LIMIT 1');
        $find->execute([$bid,$name]); $fileId=$find->fetchColumn();
        if (!$fileId) {
            $find=$pdo->prepare('SELECT file_id FROM files WHERE file_name=? AND file_dir=? LIMIT 1');
            $find->execute([$name,'sibi_docs']); $fileId=$find->fetchColumn();
        }
        if (!$fileId) {
            $st=$pdo->prepare('INSERT INTO files (file_title,file_name,file_url,file_dir,mime_type,input_date,last_update,uploader_id) VALUES (?,?,?,?,?,NOW(),NOW(),?)');
            $st->execute([$r['title'],$name,$r['attachment'],'sibi_docs','application/pdf',$uid]);
            $fileId=$pdo->lastInsertId();
        }
        $st=$pdo->prepare('SELECT 1 FROM biblio_attachment WHERE biblio_id=? AND file_id=? LIMIT 1');
        $st->execute([$bid,$fileId]);
        if (!$st->fetchColumn()) {
            $st=$pdo->prepare("INSERT INTO biblio_attachment (biblio_id,file_id,placement,access_type) VALUES (?,?,'popup','public')");
            $st->execute([$bid,$fileId]);
        }
    }
    return $result;
}
