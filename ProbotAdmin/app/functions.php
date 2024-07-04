<?php

use Illuminate\Database\Capsule\Manager as DB;

function is_assoc($arr) {
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function jsonpWrap($callback, $payload) {
    $jsonp = sprintf("%s(%s);", $callback, json_encode($payload, JSON_UNESCAPED_UNICODE));
    //header("Content-Type: application/javascript");
    return $jsonp;
}

function attachments_combine($old, $new) {
    # 合并old, new 两个list
    $o = [];
    $n = [];
    if (empty($old)) {
        $old = [];
        return $new;
    } else {
        $old = json_decode($old, true);
        # 如果old不为list，则设置为[]
        if (is_assoc($old)) {
            $old = [];
            return $new;
        }
    }
    if (empty($new)) {
        $new = [];
    } else {
        $new = json_decode($new, true);
    }
    $l_old = count($old);
    $l_new = count($new);
    $dif = $l_new - $l_old;
    if ($dif > 0) {
        for ($i = 0; $i < $dif; $i++) {
            $old[] = [];
        }
    }
    $result = [];
    $idx = 0;
    # 以新数据的结构为准，若原来相同未知有新字段，就合并保留
    foreach ($new as &$item) {
        $old_item = isset($old[$idx]) ? $old[$idx] : [];
        $new[$idx] = array_merge($old_item, $item);
        $idx++;
    }
    return json_encode($new, JSON_UNESCAPED_UNICODE);
}

// 根据$where更新或插入$table, 日志写入 $table_log
function update_table_with_log($table, $where = [], $data = [], $extra_data = [], $user = '') {
    $result = [
        'api_status' => 'success',
        'code' => 0,
        'content' => null,
    ];
    if ($user == '') {
        $user = 'api2:update_table_with_log';
    }
    $did = 'id';
    $db = null;
    $db_log = null;
    if ($table == 'drug_law_and_regulation') {
        $db = new Xanda\DrugLawRegulation;
    } elseif ($table == 'se_stock_notice') {
        $db = new Xanda\StockNotice;
    } elseif ($table == 'hdi_results') {
        $db = new LabQr\HdiResults;
        $db_log = new LabQr\HdiResultsLog;
    } elseif ($table == 'merck_index') {
        $db = new LabQr\MerckIndex;
        $db_log = new LabQr\MerckIndexLog;
    } else {
        $db = null;
    }

    if (is_null($db)) {
        $result['content'] = "unknown table: {$table}";
        $result['api_status'] = 'fail';
    } else {
        # 获取原来的数据
        $db2 = $db;
        foreach ($where as $k => $v) {
            $db2 = $db2->where($k, $v);
        }
        $old_item = $db2->select('*')->first();
        $has_new_data = false;
        $update_log_data = [];
        $id = '';
        if (!empty($old_item)) {
            $old_item = $old_item->toArray();
            $id = isset($old_item[$did]) ? $old_item[$did] : '';
            $result['content'] = $id;
            foreach ($data as $k => $v) {
                if (array_key_exists($k, $old_item)) {
                    $old_v = $old_item[$k];
                    if ($k == 'attachments') {
                        # 合并新旧attachments, 防止覆盖已经更新的dp2_attachment
                        $data[$k] = attachments_combine($old_v, $v);
                        $has_new_data = true;
                    } else {
                        if ($v != '' && $v != $old_v) {
                            $has_new_data = true;
                            array_push($update_log_data, [
                                'did' => $id,
                                'field' => $k,
                                'old_content' => $old_v,
                                'new_content' => $v,
                                'user' => $user,
                                'created_at' => date('Y-m-d H:i:s'),
                            ]);
                        } else {
                            unset($data[$k]);
                        }
                    }
                }
            }
        } else {
            $has_new_data = true;
            # 删除空白的字段
            foreach ($data as $k => $v) {
                if ($v == '') {
                    unset($data[$k]);
                }
            }
        }
        $item = [];
        if ($has_new_data) {
            $data = array_merge($data, $extra_data);
            $item = $db->updateOrCreate($where, $data);
            $result['content'] = isset($item[$did]) ? $item[$did] : '';
            $result['did'] = $did;
            // $result['where'] = $where;
            // $result['data'] = $data;
            // $result['item'] = $item;
            if (!is_null($db_log)) {
                $db_log->insert($update_log_data);
            }
        }
    }
    return $result;
}

// 替换str中的占位符
function replace_placeholders($str, $params) {
    if ($str) {
        if (preg_match_all("/{(.+?)}/", $str, $match)) {
            $keys = $match[1];
            foreach ($keys as $key) {
                if (isset($params[$key])) {
                    $str = str_replace('{' . $key . '}', $params[$key], $str);
                }
            }
        }
    }
    return $str;
}

function filter_Emoji($str) {
    $str = preg_replace_callback( //执行一个正则表达式搜索并且使用一个回调进行替换
        '/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str);

    return $str;
}

// 计算d1和d2两个日期之间的时间差：'2011-12-08 07:02:40'
function timediff($begin_time, $end_time) {
    $begin_time = strtotime($begin_time);
    $end_time = strtotime($end_time);
    if ($begin_time < $end_time) {
        $starttime = $begin_time;
        $endtime = $end_time;
    } else {
        $starttime = $end_time;
        $endtime = $begin_time;
    }
    $timediff = $endtime - $starttime;
    $days = intval($timediff / 86400);
    $remain = $timediff % 86400;
    $hours = intval($remain / 3600);
    $remain = $remain % 3600;
    $mins = intval($remain / 60);
    $secs = $remain % 60;
    $res = array("day" => $days, "hour" => $hours, "min" => $mins, "sec" => $secs);
    return $res;
}

# 获取redis键值中JSON中的keys
function get_object_keys($redis, $key) {
    // 获取忽略的study_name
    $list = $redis->get($key);
    if (empty($list)) {
        $list = '{}';
    }
    $list = json_decode($list, true);
    $projects = [];

    foreach ($list as $k => $v) {
        array_push($projects, $k);
    }
    return $projects;
}

// 生成统一的payload用于批量更新 download_pool2
function make_dp2_batch_payload($item, $params) {
    $status = isset($params['status']) ? $params['status'] : '';
    $failed_times = isset($item['failed_times']) ? $item['failed_times'] : 0;
    if ($status == 4) {
        $down_status = isset($params['down_status']) ? safe_json_decode($params['down_status']) : [];
        $params['study_status'] = 4;
        if (isset($down_status['msg'])) {
            $params['study_msg'] = $down_status['msg'];
        }
        $failed_times += 1;
    } elseif ($status == 3) {
        $failed_times = 0;
    }
    $item = array_merge($item, $params);

    return [
        "id" => $item['id'],
        "project_name" => $item['project_name'],
        "client" => $item['client'],
        "task_fp" => $item['task_fp'],
        "status" => $item['status'],
        "priority" => $item['priority'],
        "type" => $item['type'],
        "interval" => $item['interval'],

        "dp2_html" => $item['dp2_html'],
        "dp2_html_updated_at" => $item['dp2_html_updated_at'],

        "dp2_json" => $item['dp2_json'],
        "dp2_json_updated_at" => $item['dp2_json_updated_at'],

        "has_new_data" => $item['has_new_data'],
        "has_new_data_updated_at" => $item['has_new_data_updated_at'],

        "is_study_extracted" => $item['is_study_extracted'],

        "study_status" => $item['study_status'],
        "study_msg" => $item['study_msg'],

        "reparse_only" => $item['reparse_only'],
        "down_status" => $item['down_status'],
        "failed_times" => $failed_times,
        "updated_date" => $item['updated_date'] ?? date('Y-m-d'),
    ];
}

function combine_labels($lbs1, $lbs2) {
    $new_lbs = [];
    $i = 0;
    foreach ($lbs1 as &$label) {
        $new_lb = [
            'id' => $label['id'],
            'categoryId' => $label['categoryId'],
            'startIndex' => $label['startIndex'],
            'endIndex' => $label['endIndex'],
        ];
        array_push($new_lbs, $new_lb);
        if ($label['id'] >= $i) {
            $i = $label['id'] + 1;
        }
    }

    foreach ($lbs2 as &$label) {
        $new_lb = [
            'id' => $i,
            'categoryId' => $label['categoryId'],
            'startIndex' => $label['startIndex'],
            'endIndex' => $label['endIndex'],
        ];
        array_push($new_lbs, $new_lb);
        $i++;
    }
    return $new_lbs;
}

function cfda_data_pretreatment($data, $table) {
    $new_data = $data;
    if (isset($data['gcid']) && isset($data['content'])) {
        $new_data = ['gcid' => $data['gcid']];
        $content = $data['content'];
        if ($table == 'cfda_guochan') {
            $mapping = [
                "批准文号" => "license_num",
                "产品名称" => "product_name_cn",
                "英文名称" => "product_name_en",
                "商品名" => "trade_name",
                "剂型" => "dosage_form",
                "规格" => "strength",
                "上市许可持有人" => "product_license_holder",
                "生产单位" => "manufacturer",
                "生产地址" => "manufacturer_address",
                "产品类别" => "product_type",
                "批准日期" => "issue_date",
                "原批准文号" => "license_num_old",
                "药品本位码" => "native_code",
                "药品本位码备注" => "native_code_content",
                "相关数据库查询" => "related_database",
                "注" => "notes",
            ];
            foreach ($content as $item) {
                $name = isset($mapping[$item['name']]) ? $mapping[$item['name']] : '';
                $value = $item['value'];
                if ($name != '') {
                    array_push($new_data, [$name => $value]);
                }
            }
        } elseif ($table == '****') {

        }
    }
    return $new_data;
}

// 秒转化为天D,小时H, 分钟M, 秒S
function SecondsToString($seconds) {
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    $days = $dtF->diff($dtT)->format('%a');
    $hours = $dtF->diff($dtT)->format('%h');
    $minutes = $dtF->diff($dtT)->format('%i');
    $seconds = $dtF->diff($dtT)->format('%s');
    $str = '';
    if ($days > 0) {
        $str = "{$days}D";
    }
    if ($hours > 0) {
        $str .= "{$hours}H";
    }
    if ($minutes > 0) {
        $str .= "{$minutes}M";
    }
    if ($seconds > 0) {
        $str .= "{$seconds}S";
    }
    return $str;
}

function StringToSeconds($str) {
    $pattern = "/(\d+)([DHMSdhms]{1})/";
    $seconds = 0;
    if (preg_match_all($pattern, $str, $out)) {
        $nums = $out[1];
        $units = $out[2];
        foreach ($nums as $idx => $val) {
            $unit = unit_to_seconds($units[$idx]);
            $seconds += $val * $unit;
        }
    } elseif (preg_match("/^\d+$/", $str, $out)) {
        $seconds = $str;
    }
    return $seconds;
}

function unit_to_seconds($u) {
    $u = strtoupper($u);
    $mapping = [
        'S' => 1,
        'M' => 60,
        'H' => 3600,
        'D' => 86400,
    ];
    if (isset($mapping[$u])) {
        return $mapping[$u];
    } else {
        return 0;
    }
}

function mime2ext($mime) {
    $mime_map = [
        'video/3gpp2' => '3g2',
        'video/3gp' => '3gp',
        'video/3gpp' => '3gp',
        'application/x-compressed' => '7zip',
        'audio/x-acc' => 'aac',
        'audio/ac3' => 'ac3',
        'application/postscript' => 'ai',
        'audio/x-aiff' => 'aif',
        'audio/aiff' => 'aif',
        'audio/x-au' => 'au',
        'video/x-msvideo' => 'avi',
        'video/msvideo' => 'avi',
        'video/avi' => 'avi',
        'application/x-troff-msvideo' => 'avi',
        'application/macbinary' => 'bin',
        'application/mac-binary' => 'bin',
        'application/x-binary' => 'bin',
        'application/x-macbinary' => 'bin',
        'image/bmp' => 'bmp',
        'image/x-bmp' => 'bmp',
        'image/x-bitmap' => 'bmp',
        'image/x-xbitmap' => 'bmp',
        'image/x-win-bitmap' => 'bmp',
        'image/x-windows-bmp' => 'bmp',
        'image/ms-bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'application/bmp' => 'bmp',
        'application/x-bmp' => 'bmp',
        'application/x-win-bitmap' => 'bmp',
        'application/cdr' => 'cdr',
        'application/coreldraw' => 'cdr',
        'application/x-cdr' => 'cdr',
        'application/x-coreldraw' => 'cdr',
        'image/cdr' => 'cdr',
        'image/x-cdr' => 'cdr',
        'zz-application/zz-winassoc-cdr' => 'cdr',
        'application/mac-compactpro' => 'cpt',
        'application/pkix-crl' => 'crl',
        'application/pkcs-crl' => 'crl',
        'application/x-x509-ca-cert' => 'crt',
        'application/pkix-cert' => 'crt',
        'text/css' => 'css',
        'text/x-comma-separated-values' => 'csv',
        'text/comma-separated-values' => 'csv',
        'application/vnd.msexcel' => 'csv',
        'application/x-director' => 'dcr',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/x-dvi' => 'dvi',
        'message/rfc822' => 'eml',
        'application/x-msdownload' => 'exe',
        'video/x-f4v' => 'f4v',
        'audio/x-flac' => 'flac',
        'video/x-flv' => 'flv',
        'image/gif' => 'gif',
        'application/gpg-keys' => 'gpg',
        'application/x-gtar' => 'gtar',
        'application/x-gzip' => 'gzip',
        'application/mac-binhex40' => 'hqx',
        'application/mac-binhex' => 'hqx',
        'application/x-binhex40' => 'hqx',
        'application/x-mac-binhex40' => 'hqx',
        'text/html' => 'html',
        'image/x-icon' => 'ico',
        'image/x-ico' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'text/calendar' => 'ics',
        'application/java-archive' => 'jar',
        'application/x-java-application' => 'jar',
        'application/x-jar' => 'jar',
        'image/jp2' => 'jp2',
        'video/mj2' => 'jp2',
        'image/jpx' => 'jp2',
        'image/jpm' => 'jp2',
        'image/jpeg' => 'jpeg',
        'image/pjpeg' => 'jpeg',
        'application/x-javascript' => 'js',
        'application/json' => 'json',
        'text/json' => 'json',
        'application/vnd.google-earth.kml+xml' => 'kml',
        'application/vnd.google-earth.kmz' => 'kmz',
        'text/x-log' => 'log',
        'audio/x-m4a' => 'm4a',
        'application/vnd.mpegurl' => 'm4u',
        'audio/midi' => 'mid',
        'application/vnd.mif' => 'mif',
        'video/quicktime' => 'mov',
        'video/x-sgi-movie' => 'movie',
        'audio/mpeg' => 'mp3',
        'audio/mpg' => 'mp3',
        'audio/mpeg3' => 'mp3',
        'audio/mp3' => 'mp3',
        'video/mp4' => 'mp4',
        'video/mpeg' => 'mpeg',
        'application/oda' => 'oda',
        'audio/ogg' => 'ogg',
        'video/ogg' => 'ogg',
        'application/ogg' => 'ogg',
        'application/x-pkcs10' => 'p10',
        'application/pkcs10' => 'p10',
        'application/x-pkcs12' => 'p12',
        'application/x-pkcs7-signature' => 'p7a',
        'application/pkcs7-mime' => 'p7c',
        'application/x-pkcs7-mime' => 'p7c',
        'application/x-pkcs7-certreqresp' => 'p7r',
        'application/pkcs7-signature' => 'p7s',
        'application/pdf' => 'pdf',
        'application/octet-stream' => 'pdf',
        'application/x-x509-user-cert' => 'pem',
        'application/x-pem-file' => 'pem',
        'application/pgp' => 'pgp',
        'application/x-httpd-php' => 'php',
        'application/php' => 'php',
        'application/x-php' => 'php',
        'text/php' => 'php',
        'text/x-php' => 'php',
        'application/x-httpd-php-source' => 'php',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'application/powerpoint' => 'ppt',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.ms-office' => 'ppt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/x-photoshop' => 'psd',
        'image/vnd.adobe.photoshop' => 'psd',
        'audio/x-realaudio' => 'ra',
        'audio/x-pn-realaudio' => 'ram',
        'application/x-rar' => 'rar',
        'application/rar' => 'rar',
        'application/x-rar-compressed' => 'rar',
        'audio/x-pn-realaudio-plugin' => 'rpm',
        'application/x-pkcs7' => 'rsa',
        'text/rtf' => 'rtf',
        'text/richtext' => 'rtx',
        'video/vnd.rn-realvideo' => 'rv',
        'application/x-stuffit' => 'sit',
        'application/smil' => 'smil',
        'text/srt' => 'srt',
        'image/svg+xml' => 'svg',
        'application/x-shockwave-flash' => 'swf',
        'application/x-tar' => 'tar',
        'application/x-gzip-compressed' => 'tgz',
        'image/tiff' => 'tiff',
        'text/plain' => 'txt',
        'text/x-vcard' => 'vcf',
        'application/videolan' => 'vlc',
        'text/vtt' => 'vtt',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/wav' => 'wav',
        'application/wbxml' => 'wbxml',
        'video/webm' => 'webm',
        'audio/x-ms-wma' => 'wma',
        'application/wmlc' => 'wmlc',
        'video/x-ms-wmv' => 'wmv',
        'video/x-ms-asf' => 'wmv',
        'application/xhtml+xml' => 'xhtml',
        'application/excel' => 'xl',
        'application/msexcel' => 'xls',
        'application/x-msexcel' => 'xls',
        'application/x-ms-excel' => 'xls',
        'application/x-excel' => 'xls',
        'application/x-dos_ms_excel' => 'xls',
        'application/xls' => 'xls',
        'application/x-xls' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-excel' => 'xlsx',
        'application/xml' => 'xml',
        'text/xml' => 'xml',
        'text/xsl' => 'xsl',
        'application/xspf+xml' => 'xspf',
        'application/x-compress' => 'z',
        'application/x-zip' => 'zip',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/s-compressed' => 'zip',
        'multipart/x-zip' => 'zip',
        'text/x-scriptzsh' => 'zsh',
    ];

    return isset($mime_map[$mime]) === true ? $mime_map[$mime] : '';
}

function get_public_env_for_debug($env) {
    $data = array();
    foreach ($env as $k => $v) {
        $pattern = "/^MYSQL/";
        if (!preg_match($pattern, $k, $match_out)) {
            if (!in_array($k, ['JWT_SECRET', 'REDIS_HOST', 'REDIS_PORT', 'ES_HOST', 'INFOCUS_MCH_KEY', 'ACCESS_KEY_ID', 'SECRET_ACCESS_KEY', 'USER', 'BUSER', 'CUSER', 'DUSER', 'HOST', 'BHOST', 'CHOST', 'DHOST', 'PASS', 'DBNAME', 'BDBNAME', 'CDBNAME', 'DDBNAME', 'PASS', 'BPASS', 'CPASS', 'DPASS', 'IV', 'EPASS', 'cookieSecret', 'DESKEY', 'SMS_KEY', 'appid', 'appsecret', 'SMS_USER', 'EHOST', 'EPORT', 'EDBNAME', 'EUSER'])) {
                $data[$k] = $v;
            }
        }
    }
    return $data;
}

function array_to_unique_hash($arr) {
    if (!is_array($arr)) {
        return $arr;
    }
    ksort($arr);
    $char_str = '';
    foreach ($arr as $k => $v) {
        if (is_array($v)) {
            $v = array_to_unique_hash($v);
        }
        $char_str .= "$k=$v&";
    }
    return $char_str;
}

# 生成dp2任务的指纹 task_fp: project_name|url|data(k=v&k1=v1)
function make_task_fingerprint_2($params) {
    $url = isset($params['url']) ? $params['url'] : '';
    $project_name = isset($params['project_name']) ? $params['project_name'] : '';
    $method = isset($params['method']) ? $params['method'] : '';
    $data = isset($params['data']) ? $params['data'] : '';
    $char_str = "$project_name|$url|";
    if ($method == 'POST' && $data != '' && preg_match("/^{.+/", $data, $match_out)) {
        $data = json_decode($data, true);
        $char_str .= array_to_unique_hash($data);
    }
    return $char_str;
}
function make_task_fingerprint($params) {
    $url = isset($params['url']) ? $params['url'] : '';
    $project_name = isset($params['project_name']) ? $params['project_name'] : '';
    $method = isset($params['method']) ? $params['method'] : '';
    $data = isset($params['data']) ? $params['data'] : '';
    $char_str = "$project_name|$url|";
    if ($method == 'POST' && $data != '' && preg_match("/^{.+/", $data, $match_out)) {
        $data = json_decode($data, true);
        $char_str .= array_to_unique_hash($data);
    }
    return md5($char_str);
}

function getDatabaseName() {
    $uri = getenv('REQUEST_URI');
    preg_match_all("/^\/(.+?)\//", $uri, $pat_array);
    if (!empty($pat_array[1])) {
        $dbtag = $pat_array[1][0];
        if ($dbtag == 'a' || $dbtag == 'b' || $dbtag == 'c' || $dbtag == 'd' || $dbtag == 'e') {
            return 'db' . $dbtag;
        }
    }
    return 'dba';
}

function make_study_search_sql($params) {
    // 单选
    $id = isset($params['id']) ? $params['id'] : '';
    $study_name = isset($params['study_name']) ? $params['study_name'] : '';
    $status = isset($params['status']) ? $params['status'] : '';
    $study_note = isset($params['study_note']) ? $params['study_note'] : '';
    $order_by = isset($params['order_by']) ? $params['order_by'] : 'id';
    $direction = isset($params['direction']) ? $params['direction'] : 'desc';
    $exact = isset($params['exact']) ? $params['exact'] : '';
    $study_content = $params['study_content'] ?? '';
    $added_by = $params['added_by'] ?? '';
    $last_user = $params['last_user'] ?? '';

    $dl = new Xanda\StudyView;
    if ($id != '') {
        $id = addslashes($id);
        $dl = $dl->where('id', "$id");
    }
    if ($exact == '') {
        $dl = make_like_where($dl, 'study_name', $study_name);
        $dl = make_like_where($dl, 'study_note', $study_note);
    } else {
        $dl = make_where_by_varible_type($dl, 'study_name', $study_name);
        $dl = make_where_by_varible_type($dl, 'study_note', $study_note);
    }
    if ($study_content != '') {
        $phrase = addslashes($study_content);
        $dl = $dl->whereRaw("match(study_json,study_note,study_name) against ('\"$phrase\"' in boolean mode)");
    }
    if ($added_by != '') {
        $dl = $dl->where('added_by', $added_by);
    }
    if ($last_user != '') {
        $dl = $dl->where('last_user', $last_user);
    }
    if ($status != '') {
        $status = addslashes($status);
        $dl = $dl->where('status', "$status");
    }
    if ($order_by != '') {
        $dl = $dl->orderBy($order_by, $direction);
    }
    return $dl;
}

function make_crontask_search_sql($params) {
    // 单选
    $id = isset($params['id']) ? $params['id'] : '';
    $server = isset($params['server']) ? $params['server'] : '';
    $task_name = isset($params['task_name']) ? $params['task_name'] : '';
    $status = isset($params['status']) ? $params['status'] : '';
    $run_msg = isset($params['run_msg']) ? $params['run_msg'] : '';
    $status_msg = isset($params['status_msg']) ? $params['status_msg'] : '';
    $order_by = isset($params['order_by']) ? $params['order_by'] : '';
    $direction = isset($params['direction']) ? $params['direction'] : 'desc';

    $dl = new Xanda\CronTask;
    if ($id != '') {
        $id = addslashes($id);
        $dl = $dl->where('id', "$id");
    }
    $dl = make_like_where($dl, 'task_name', $task_name);
    $dl = make_like_where($dl, 'server', $server);
    $dl = make_like_where($dl, 'run_msg', $run_msg);
    $dl = make_like_where($dl, 'status_msg', $status_msg);

    if ($status != '') {
        $status = addslashes($status);
        $dl = $dl->where('status', "$status");
    }
    if ($order_by != '') {
        $dl = $dl->orderBy($order_by, $direction);
    }

    return $dl;
}

function make_dtt_search_sql($params) {
    // 单选
    $id = isset($params['id']) ? $params['id'] : '';
    $task_name = isset($params['task_name']) ? $params['task_name'] : '';
    $status = isset($params['status']) ? $params['status'] : '';
    $task_note = isset($params['task_note']) ? $params['task_note'] : '';
    $task_json = $params['task_json'] ?? '';
    $priority = isset($params['priority']) ? $params['priority'] : '';
    $order_by = isset($params['order_by']) ? $params['order_by'] : 'id';
    $direction = isset($params['direction']) ? $params['direction'] : 'desc';
    $running_on = $params['running_on'] ?? '';

    $dl = new Xanda\DTT;
    if ($id != '') {
        $id = addslashes($id);
        $dl = $dl->where('id', "$id");
    }
    $dl = make_like_where($dl, 'task_name', $task_name);
    $dl = make_like_where($dl, 'task_note', $task_note);
    $dl = make_like_where($dl, 'task_json', $task_json);

    if ($status != '') {
        $status = addslashes($status);
        $dl = $dl->where('status', "$status");
    }
    if ($running_on != '') {
        $running_on = addslashes($running_on);
        $dl = $dl->where('running_on', "$running_on");
    }
    if ($priority != '') {
        $dl = $dl->where('priority', "$priority");
    }
    if ($order_by != '') {
        $dl = $dl->orderBy($order_by, $direction);
    }

    return $dl;
}

// $obj为字串时，json_decode,为object直接返回
function safe_json_decode($obj) {
    if (is_string($obj)) {
        return json_decode($obj, true);
    } else {
        return $obj;
    }
}

function make_hdi_search_sql($params) {
    // 单选
    $id = isset($params['id']) ? $params['id'] : '';
    $PMID = isset($params['PMID']) ? $params['PMID'] : '';
    $item = isset($params['item']) ? $params['item'] : '';
    $ArticleTitle = isset($params['ArticleTitle']) ? $params['ArticleTitle'] : '';
    $AbstractText = isset($params['AbstractText']) ? $params['AbstractText'] : '';
    $hdi_class = isset($params['hdi_class']) ? $params['hdi_class'] : '';
    $ai_hdi_class = isset($params['ai_hdi_class']) ? $params['ai_hdi_class'] : '';
    $ai_hdi_class_2 = isset($params['ai_hdi_class_2']) ? $params['ai_hdi_class_2'] : '';
    $last_edited_by = isset($params['last_edited_by']) ? $params['last_edited_by'] : '';
    $source = isset($params['source']) ? $params['source'] : '';
    $is_50herbs = isset($params['is_50herbs']) ? $params['is_50herbs'] : '';

    $mesh_term = isset($params['mesh_term']) ? $params['mesh_term'] : '';

    if ($hdi_class == '-1') {
        $hdi_class = '';
    }
    if ($ai_hdi_class == '-1') {
        $ai_hdi_class = '';
    }
    if ($ai_hdi_class_2 == '-1') {
        $ai_hdi_class_2 = '';
    }

    $order_by = isset($params['order_by']) ? $params['order_by'] : '';
    $direction = isset($params['direction']) ? $params['direction'] : 'desc';

    $gj = new LabQr\HdiRef;
    if ($id != '') {
        $ids = explode(',', $id);
        $gj = $gj->whereIn('id', $ids);
    }
    if ($PMID != '') {
        $gj = $gj->where('PMID', $PMID);
    }
    if ($source != '') {
        $source = addslashes($source);
        $gj = $gj->where('source', $source);
    }
    #$gj = make_like_where($gj, 'PMID', $PMID);
    #$gj = make_where_by_varible_type($gj, 'item', $item);
    if ($item != '') {
        $item = addslashes($item);
        $gj = $gj->whereRaw("match(item) against ('$item')");
    }
    if ($ArticleTitle != '') {
        $ArticleTitle = addslashes($ArticleTitle);
        $gj = $gj->whereRaw("match(ArticleTitle) against ('$ArticleTitle')");
    }
    if ($AbstractText != '') {
        $AbstractText = addslashes($AbstractText);
        $gj = $gj->whereRaw("match(AbstractText) against ('$AbstractText')");
    }
    // $gj = make_like_where($gj, 'ArticleTitle', $ArticleTitle);
    // $gj = make_like_where($gj, 'AbstractText', $AbstractText);
    $gj = make_where_by_varible_type($gj, 'is_50herbs', $is_50herbs);
    $gj = make_where_by_varible_type($gj, 'hdi_class', $hdi_class);
    $gj = make_where_by_varible_type($gj, 'ai_hdi_class', $ai_hdi_class);
    $gj = make_where_by_varible_type($gj, 'ai_hdi_class_2', $ai_hdi_class_2);
    $gj = make_where_by_varible_type($gj, 'last_edited_by', $last_edited_by);

    if ($order_by != '') {
        $gj = $gj->orderBy(DB::raw($order_by), $direction);
    }
    return $gj;
}

function make_where_by_varible_type($db, $name, $value) {
    if ($value != '') {
        if (is_array($value)) {
            $db = $db->whereIn($name, $value);
        } else {
            $db = $db->where($name, '=', $value);
        }
    }
    return $db;
}

function make_like_where($db, $name, $value) {
    if ($value != '') {
        $value = addslashes($value);
        $db = $db->where($name, 'LIKE', "%$value%");
    }
    return $db;
}

function format_item_name($item_name) {
    $item_name = preg_replace("/(\d+)(G|g)/m", "$1 $2", trim($item_name));
    $item_name = preg_replace("/(\d+)\s*(mg|ml)/m", "$1 $2", $item_name);
    $item_name = preg_replace("/(\d+)\s*u(\w{1})/m", "$1 μ$2", $item_name);
    return $item_name;
}

function get_pagination($offset, $tnum) {
    $cpg = floor($offset / 10) + 1;
    $ftpg = $tnum / 10;
    $tpg = $ftpg;
    if (floor($ftpg) != $ftpg) {
        $tpg = floor($ftpg) + 1;
    }
    $npg = $cpg + 1;
    if ($npg > $tpg) {
        $npg = $tpg;
    }
    $ppg = $cpg - 1;
    if ($ppg < 1) {
        $ppg = 1;
    }
    return ['cpg' => $cpg, 'npg' => $npg, 'tpg' => $tpg, 'ppg' => $ppg, 'tnum' => $tnum, 'offset' => $offset];
}

function make_lexicon_search_sql($params, $unionid) {
    // 单选
    $id = isset($params['id']) ? $params['id'] : '';
    $std_item = isset($params['std_item']) ? $params['std_item'] : '';
    #$item = isset($params['item']) ? $params['item'] : '';
    $type = isset($params['type']) ? $params['type'] : '';
    $language = isset($params['language']) ? $params['language'] : '';
    $order_by = isset($params['order_by']) ? $params['order_by'] : '';
    $direction = isset($params['direction']) ? $params['direction'] : 'desc';

    $dl = new Xanda\LexiconDrugs;
    $dl = $dl->where('is_active', '1');

    if ($id != '') {
        $id = addslashes($id);
        $dl = $dl->where('id', 'LIKE', "%$id%");
    }
    if ($std_item != '') {
        $std_item = addslashes($std_item);
        $dl = $dl->where('std_item', 'LIKE', "%$std_item%");
    }
    if ($type != '') {
        $type = addslashes($type);
        $dl = $dl->where('type', $type);
    }
    if ($language != '') {
        $language = addslashes($language);
        $dl = $dl->where('language', $language);
    }
    if ($order_by != '') {
        $dl = $dl->orderBy($order_by, $direction);
    }

    return $dl;
}

function make_download_pool2_search_sql($params, $unionid) {
    // 单选
    $id = isset($params['id']) ? $params['id'] : '';
    $task_fp = $params['task_fp'] ?? '';
    $study_name = isset($params['study_name']) ? $params['study_name'] : '';
    $project_name = isset($params['project_name']) ? $params['project_name'] : '';
    $status = isset($params['status']) ? $params['status'] : '';
    $client = isset($params['client']) ? $params['client'] : '';
    $parent_id = $params['parent_id'] ?? '';
    $extra_data = isset($params['extra_data']) ? $params['extra_data'] : '';
    $excluded_workers = isset($params['excluded_workers']) ? $params['excluded_workers'] : '';
    $is_study_extracted = isset($params['is_study_extracted']) ? $params['is_study_extracted'] : '';
    $has_new_data = isset($params['has_new_data']) ? $params['has_new_data'] : '';
    $dp2_html = isset($params['dp2_html']) ? $params['dp2_html'] : '';
    $dp2_json = isset($params['dp2_json']) ? $params['dp2_json'] : '';
    $study_status = isset($params['study_status']) ? $params['study_status'] : '';
    $failed_times = isset($params['failed_times']) ? $params['failed_times'] : '';
    $priority = isset($params['priority']) ? $params['priority'] : '';
    $order_by = isset($params['order_by']) ? $params['order_by'] : '';
    $direction = isset($params['direction']) ? $params['direction'] : 'desc';
    $exact = isset($params['exact']) ? $params['exact'] : '';

    $dl = new Xanda\DownPool2;
    // if ($id == '') {
    //     $dl = $dl->from(DB::RAW('download_pool2 FORCE INDEX(multi_fields)'));
    // }

    if ($id != '') {
        $ids = explode(',', $id);
        $dl = $dl->whereIn('id', $ids);
    }
    if ($task_fp != '') {
        $task_fp = addslashes($task_fp);
        $dl = $dl->where('task_fp', "$task_fp");
    }
    if ($parent_id != '') {
        $parent_id = addslashes($parent_id);
        $dl = $dl->where('parent_id', "$parent_id");
    }
    if ($study_name != '') {
        $study_name = addslashes($study_name);
        if ($exact == '') {
            $db = new Xanda\DP2SN;
            $items = $db->select('study_name')->where('study_name', 'LIKE', "%$study_name%")->get();
            $dl = $dl->whereIn('study_name', $items);
        } else {
            $dl = $dl->where('study_name', $study_name);
        }
    }
    if ($project_name != '') {
        $project_name = addslashes($project_name);
        if ($exact == '') {
            $db = new Xanda\DP2PN;
            $items = $db->select('project_name')->where('project_name', 'LIKE', "%$project_name%")->get();
            $dl = $dl->whereIn('project_name', $items);
        } else {
            $dl = $dl->where('project_name', $project_name);
        }
    }
    if ($client != '') {
        $client = addslashes($client);
        if ($exact == '') {
            $db = new Xanda\DP2Client;
            $items = $db->select('client')->where('client', 'LIKE', "%$client%")->get();
            $dl = $dl->whereIn('client', $items);
        } else {
            $dl = $dl->where('client', $client);
        }
    }
    if ($extra_data != '') {
        $extra_data = addslashes($extra_data);
        $dl = $dl->whereRaw("match(extra_data) against ('$extra_data')");
    }
    if ($excluded_workers != '') {
        $excluded_workers = addslashes($excluded_workers);
        $dl = $dl->whereRaw("match(excluded_workers) against ('$excluded_workers')");
    }
    if ($status != '') {
        $status = addslashes($status);
        $dl = $dl->where('status', "$status");
    }
    if ($is_study_extracted != '') {
        $dl = $dl->where('is_study_extracted', "$is_study_extracted");
    }
    if ($has_new_data != '') {
        $dl = $dl->where('has_new_data', "$has_new_data");
    }
    if ($dp2_html != '') {
        $dl = $dl->where('dp2_html', "$dp2_html");
    }
    if ($dp2_json != '') {
        $dl = $dl->where('dp2_json', "$dp2_json");
    }
    if ($study_status != '') {
        $dl = $dl->where('study_status', "$study_status");
    }
    if ($failed_times != '') {
        $dl = $dl->where('failed_times', "$failed_times");
    }
    if ($priority != '') {
        $dl = $dl->where('priority', "$priority");
    }

    //$dl = $dl->where('is_active', '1');
    //if ($unionid != '' && $unionid != 'o0b-qs1_XYdF2tsBM47xz6-7wYhQ') {
    // $dl = $dl->where(function ($query) use ($unionid) {
    //     $query->where('unionid', '=', $unionid)->orWhere('unionid', '=', 'ALL');
    // });
    //}
    if ($order_by != '') {
        $dl = $dl->orderBy($order_by, $direction);
    }

    return $dl;
}

// $content: 字符串或文件句柄
// $key drugsea下面的路径
function save_to_qs($qs, $key, $content, $content_type = 'text/html', $overwrite = false) {
    // 先检查 $key 是否存在，只有不存在时或$overwrite=true时才写入
    $res = $qs->headObject($key);
    if ($overwrite == true || $res->statusCode != 200) {
        $res = $qs->putObject(
            $key,
            array(
                'body' => $content,
                'Content-Type' => $content_type,
            )
        );
        # 成功返回201
        return $res->statusCode;
    } else {
        return 200;
    }
}

function http_request_post_json($url, $data = null, $cookieStr = null, $header = null) {
    $json = array('api_status' => 'success');
    try {
        $html = https_request($url, $data, $cookieStr, $header);
        if (strpos($html, 'api_status') !== false) {
            $json = json_decode($html, true);
        } else {
            $json = ['api_status' => 'fail', 'code' => 0, 'content' => $html];
            if (strpos($html, '链接不存在或已经失效') !== false) {
                $json['content'] = '链接不存在或已经失效';
            }
        }
    } catch (Exception $e) {
        return ['api_status' => 'fail', 'code' => 1000, 'content' => $html, 'error' => $e->getMessage()];
    }
    return $json;
}

function http_request_json($url, $cookieStr = null, $header = null) {
    $json = array('api_status' => 'success');
    try {
        $html = https_request($url, null, $cookieStr, $header);
        if (strpos($html, 'api_status') !== false) {
            $json = json_decode($html, true);
        } else {
            $json = ['api_status' => 'fail', 'code' => 0, 'content' => $html];
            if (strpos($html, '链接不存在或已经失效') !== false) {
                $json['content'] = '链接不存在或已经失效';
            }
        }
    } catch (Exception $e) {
        return ['api_status' => 'fail', 'code' => 1000, 'content' => $html, 'error' => $e->getMessage()];
    }
    return $json;
}

# 返回response对应的object, 参数与https_request相同
function https_request_object($url, $data = null, $cookie = null, $header = null) {
    try {
        $headers = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:  ')); //设置header
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // this function is called by curl for each header received
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) {
                    return $len;
                }

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );

        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        $output = curl_exec($ch);
        if ($output === false) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }
        curl_close($ch);
        return [
            'headers' => $headers,
            'html' => $output,
        ];
    } catch (Exception $e) {
        return ['headers' => [], 'html' => sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage())];
    }
}

function https_request($url, $data = null, $cookie = null, $header = null) {
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        #curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        #curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:  ')); //设置header
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        //if ($header) {
        // curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        //     'X-Forwarded-For: kinginsun',
        // ));
        //}
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if (!empty($cookie)) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        $output = curl_exec($curl);
        if ($output === false) {
            throw new Exception(curl_error($curl), curl_errno($curl));
        }
        curl_close($curl);
        return $output;
    } catch (Exception $e) {
        return sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage());
    }
}

function https_json_request($url, $data = null, $cookie = null, $header = null) {
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        #curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        #curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:  ')); //设置header
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        }
        // if ($header) {
        //     curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        //         'X-Apple-Tz: 0',
        //         'X-Apple-Store-Front: 143444,12',
        //     ));
        // }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if (!empty($cookie)) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        $output = curl_exec($curl);
        if ($output === false) {
            throw new Exception(curl_error($curl), curl_errno($curl));
        }
        curl_close($curl);
        return $output;
    } catch (Exception $e) {
        return sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage());
    }
}

// 用户真实IP地址： 从db.drugsea.cn的代理后面获取
function get_real_ip() {
    $onlineip = '';
    if (isset($_SERVER['HTTP_X_REALS_IP'])) {
        $onlineip = $_SERVER['HTTP_X_REALS_IP'];
    } elseif (isset($_SERVER['HTTP_ALI_CDN_REAL_IP'])) {
        $onlineip = $_SERVER['HTTP_ALI_CDN_REAL_IP'];
    } else {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $onlineip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $onlineip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        }
    }
    return $onlineip;
}

# 直接访问时获取
function get_real_ip_from_api() {
    $onlineip = '';
    if (isset($_SERVER['HTTP_ALI_CDN_REAL_IP'])) {
        $onlineip = $_SERVER['HTTP_ALI_CDN_REAL_IP'];
    } else {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $onlineip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $onlineip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        }
    }
    return $onlineip;
}

function get_browser_ua() {
    return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
}

function get_one_proxy($id, $source = '') {
    $url = "http://www.xanda.cn/xd_get_one_proxy.pl";
    $url .= "?bproxy={$id}&source={$source}";
    $html = https_request($url);
    $json = json_decode($html, true);
    if ($json['api_status'] == 'success') {
        return $json['content'];
    }
    return '';
}

function get_array_value($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

function getParamValue($params, $field, $default = '') {
    if (!empty($params[$field])) {
        return $params[$field];
    } else {
        return $default;
    }
}

// $item[$key] 可能为array，Object, string
function get_item_value($item, $key) {
    if (is_object($item[$key])) {
        $val = $item[$key];
        return isset($val->text) ? $val->text : '';
    } elseif (is_array($item[$key])) {
        return $item[$key][0];
    } else {
        return $item[$key];
    }
}