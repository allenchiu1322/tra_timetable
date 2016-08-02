<?php
class Timetable_model extends CI_Model {

    //固定資料(目前有車站和車種)
    public $f_class = FALSE;
    public $f_station = FALSE;

    //快取設定
    public $cache_dir = 'cache/';
    public $cache_expire = 86400;

    //是否輸出訊息
    public $enable_output = TRUE;

    public function __construct() {
        parent::__construct();
        $this->load->database();
        if ($this->input->is_cli_request()) {
            $this->cli_mode = TRUE;
        }
        //快取設定
        $this->cache_expire = 86400 * 14;
        //讀取車種和車站資料
        $this->f_class = $this->get_fixed_data('class');
        $this->f_station = $this->get_fixed_data('station');
    }

    private $cli_mode = FALSE;

    public function test_this() {
        return 'orz';
    }

    public function parse_xml_file($file) {
        if (file_exists($file)) {
            //刪除過時資料
            $yesterday = date('Y-m-d', time() - 86400);
            $this->db->where(sprintf("date < '%s'", $yesterday));
            $this->out(sprintf('資料庫清除結果: %s', $this->db->delete('tra_train')));
            $this->db->flush_cache();
            $this->db->where(sprintf("departure_time < '%s'", $yesterday));
            $this->out(sprintf('資料庫清除結果: %s', $this->db->delete('tra_time')));
            $this->db->flush_cache();
            $filename = basename($file);
            $train_timestamp = mktime(0, 0, 0, 
                               substr($filename, 4, 2), 
                               substr($filename, 6, 2), 
                               substr($filename, 0, 4));
            $train_date = date('Y-m-d', $train_timestamp);
            $train_date_after = date('Y-m-d', $train_timestamp + 86400);
            $xml = simplexml_load_file($file);
            $this->out(sprintf('資料庫清除結果: %s', $this->db->delete('tra_train', array('file' => $filename))));
            $this->out(sprintf('資料庫清除結果: %s', $this->db->delete('tra_time', array('file' => $filename))));
            $i = 0;
            $i_all = sizeof($xml->TrainInfo);
            foreach($xml->TrainInfo as $k => $v) {
                $i++;
                //車次資料
                $v_attr = $v->attributes();
                $this->out(sprintf('車種: %s', $this->ref($v_attr, 'CarClass')), FALSE);
                $this->out(sprintf('殘障車: %s', $this->ref($v_attr, 'Cripple')), FALSE);
                $this->out(sprintf('餐車: %s', $this->ref($v_attr, 'Dinning')), FALSE);
                $this->out(sprintf('經由: %s', $this->ref($v_attr, 'Line')), FALSE);
                $this->out(sprintf('行駛方向: %s', $this->ref($v_attr, 'LineDir')), FALSE);
                $this->out(sprintf('備註: %s', $this->ref($v_attr, 'Note')), FALSE);
                $this->out(sprintf('跨夜車站: %s', $this->ref($v_attr, 'OverNightStn')), FALSE);
                $this->out(sprintf('辦理托運: %s', $this->ref($v_attr, 'Package')), FALSE);
                $this->out(sprintf('行駛路線: %s', $this->ref($v_attr, 'Route')), FALSE);
                $this->out(sprintf('車次: %s', $this->ref($v_attr, 'Train')), FALSE);
                $this->out(sprintf('狀態: %s', $this->ref($v_attr, 'Type')));

                $data = array();
                $data['date'] = $train_date;
                $data['car_class'] = (string)$v_attr['CarClass'];
                $data['cripple'] = (string)$v_attr['Cripple'];
                $data['dinning'] = (string)$v_attr['Dinning'];
                $data['line'] = (string)$v_attr['Line'];
                $data['line_dir'] = (string)$v_attr['LineDir'];
                $data['note'] = (string)$v_attr['Note'];
                $data['overnight_station'] = (string)$v_attr['OverNightStn'];
                $data['package'] = (string)$v_attr['Package'];
                $data['route'] = (string)$v_attr['Route'];
                $data['train'] = (string)$v_attr['Train'];
                $data['type'] = (string)$v_attr['Type'];
                $data['file'] = $filename;

                $this->out(sprintf('資料庫寫入結果: %s', $this->db->insert('tra_train', $data)));
                $train_id_db = $this->db->insert_id();
                if (isset($v->TimeInfo)) {
                    //時刻
                    $day = FALSE;
                    $j = 0;
                    $j_all = sizeof($v->TimeInfo);
                    $time1 = '';
                    $time2 = '';
                    $begin_station = '';
                    $end_station = '';
                    $end_station_order = 0;
                    foreach($v->TimeInfo as $k2 => $v2) {
                        $j++;
                        $this->out(sprintf('[%d/%d][%d/%d]', $i, $i_all, $j, $j_all), FALSE);
                        $v2_attr = $v2->attributes();
                        $this->out(sprintf('抵達: %s', $this->ref($v2_attr, 'ARRTime')), FALSE);
                        $this->out(sprintf('開車: %s', $this->ref($v2_attr, 'DEPTime')), FALSE);
                        $this->out(sprintf('順序: %s', $this->ref($v2_attr, 'Order')), FALSE);
                        $this->out(sprintf('路線: %s', $this->ref($v2_attr, 'Route')), FALSE);
                        $this->out(sprintf('車站: %s', $this->ref($v2_attr, 'Station')));
                        //記錄始發站和終點站
                        if ($v2_attr['Order'] == '1') {
                            $begin_station = $v2_attr['Station'];
                        }
                        if (intval($v2_attr['Order']) > $end_station_order) {
                            $end_station = $v2_attr['Station'];
                            $end_station_order = intval($v2_attr['Order']);
                        }

                        $data = array();
                        $data['train'] = $train_id_db;
                        $data['train_tra'] = (string)$v_attr['Train'];
                        //記錄抵達和開車時間, 用來判斷跨日
                        $time1 = $time2;
                        $time2 = $this->ref($v2_attr, 'ARRTime');
                        //檢查是否跨日
                        if (!$day) {
                            $day = $this->overnight($time1, $time2);
                        }
                        if ($day) {
                            $data['arrive_time'] = sprintf('%s %s', $train_date_after, $v2_attr['ARRTime']);
                        } else {
                            $data['arrive_time'] = sprintf('%s %s', $train_date, $v2_attr['ARRTime']);
                        }
                        //記錄抵達和開車時間, 用來判斷跨日
                        $time1 = $time2;
                        $time2 = $this->ref($v2_attr, 'DEPTime');
                        //檢查是否跨日
                        if (!$day) {
                            $day = $this->overnight($time1, $time2);
                        }
                        if ($day) {
                            $data['departure_time'] = sprintf('%s %s', $train_date_after, $v2_attr['DEPTime']);
                        } else {
                            $data['departure_time'] = sprintf('%s %s', $train_date, $v2_attr['DEPTime']);
                        }
                        $data['order'] = (string)$v2_attr['Order'];
                        $data['route'] = (string)$v2_attr['Route'];
                        $data['station'] = (string)$v2_attr['Station'];
                        $data['file'] = $filename;
                        $this->out(sprintf('資料庫寫入結果: %s', $this->db->insert('tra_time', $data)));
                    }
                    //寫入始發站和終點站
                    $this->out(sprintf('%s車次 %s → %s', (string)$v_attr['Train'], $this->f_station[intval($begin_station)]->chinese, $this->f_station[intval($end_station)]->chinese));
                    $data = array();
                    $data['begin_station'] = $begin_station;
                    $data['end_station'] = $end_station;
                    $this->db->flush_cache();
                    $this->db->where('id', $train_id_db);
                    $this->out(sprintf('資料庫寫入結果: %s', $this->db->update('tra_train', $data)));
                }
            }
            $this->out(sprintf('檔案刪除結果: %s', unlink($file)));
        } else {
            $this->out('檔案不存在');
        }
    }

    public function pids($line_dir = '1', $station = '1008', $delay = 0) {
        $line = array(
            '0' => '',
            '1' => '山',
            '2' => '海',
        );

        //避免無效的車站資訊
        $station = $this->check_station($station);

        $sql = "SELECT ";
        $sql .= "tra_train.id, tra_train.end_station, tra_train.line, ";
        $sql .= "tra_train.car_class, tra_time.departure_time, ";
        $sql .= "tra_train.train, tra_train.line_dir ";
        $sql .= "FROM tra_timetable.tra_time,tra_timetable.tra_train WHERE 1 ";
        $sql .= "AND tra_time.train = tra_train.id ";
        $sql .= "AND tra_train.line_dir = '%s' ";
        $sql .= "AND tra_time.station = '%s' ";
        $sql .= "AND tra_train.end_station != '%s' ";
        $sql .= "AND tra_time.departure_time > '%s' "; 
        $sql .= "ORDER BY departure_time asc ";
        $sql .= "LIMIT 2 ";
        $sql = sprintf($sql, $line_dir, $station, $station, date('Y-m-d H:i:s', time() - (60 * $delay)));
        $query = $this->db->query($sql);
        $ret = $query->result_array();
        if (sizeof($ret) == 2) {
            for($i = 0; $i <= 1; $i++) {
                if ($i == 0) {
                    //英文跑馬燈部份
                    $ret[$i]['end_station_eng'] = $this->f_station[intval($ret[$i]['end_station'])]->english;
                    $ret[$i]['car_class_eng'] = $this->alt_class($this->f_class[intval($ret[$i]['car_class'])]->english);
                }
                $ret[$i]['end_station'] = $this->f_station[intval($ret[$i]['end_station'])]->chinese;
                $ret[$i]['line'] = $line[$ret[$i]['line']];
                $ret[$i]['car_class'] = $this->alt_class($this->f_class[intval($ret[$i]['car_class'])]->chinese);
                $ret[$i]['departure_time'] = substr($ret[$i]['departure_time'], 11, 5);
                if ($i == 0) {
                    //查沿途停靠站
                    $sql2 = "SELECT ";
                    $sql2 .= "tra_time.station ";
                    $sql2 .= "FROM tra_timetable.tra_time WHERE 1 ";
                    $sql2 .= "AND tra_time.train = %d ";
                    $sql2 .= "ORDER BY `order` asc ";
                    $sql2 .= "LIMIT 9999 ";
                    $sql2 = sprintf($sql2, $ret[$i]['id']);
                    $query2 = $this->db->query($sql2);
                    $ret2 = $query2->result_array();
                    $stops = '';
                    $ok = FALSE;
                    foreach ($ret2 as $v) {
                        if ($ok) {
                            //避免出現不存在的車站
                            if (isset($this->f_station[intval($v['station'])]->chinese)) {
                                if ($stops != '') {
                                    $stops .= ', ';
                                }
                                $stops .= $this->f_station[intval($v['station'])]->chinese;
                            }
                        }
                        if ($ok == FALSE) {
                            if($v['station'] == $station) {
                                $ok = TRUE;
                            }
                        }
                    }
                    $ret[$i]['stops'] = $stops;
                }
            }
            //組合輸出字串
            $delimiter = '!';
            $output = $ret[0]['end_station'] . $delimiter;
            $output .= $ret[0]['line'] . $delimiter;
            $output .= $ret[0]['car_class'] . $delimiter;
            $output .= $ret[0]['departure_time'] . $delimiter;
            $output .= $ret[0]['stops'] . $delimiter;
            $output .= $ret[1]['departure_time'] . $delimiter;
            $output .= $ret[1]['line'] . $delimiter;
            $output .= $ret[1]['end_station'] . $delimiter;
            $output .= $ret[1]['car_class'] . $delimiter;
            $output .= $ret[0]['car_class_eng'] . $delimiter;
            $output .= $ret[0]['end_station_eng'] . $delimiter;
            $output .= $ret[0]['train'] . $delimiter;
            $output .= $ret[0]['line_dir'] . $delimiter;
        } else {
            //組合輸出字串
            $delimiter = '!';
            $output = '----' . $delimiter;
            $output .= '--' . $delimiter;
            $output .= '----' . $delimiter;
            $output .= date('H:i', time()) . $delimiter;
            $output .= '本方向無任何列車' . $delimiter;
            $output .= date('H:i', time() + 60) . $delimiter;
            $output .= '--' . $delimiter;
            $output .= '----' . $delimiter;
            $output .= '----' . $delimiter;
            $output .= '-' . $delimiter;
            $output .= '-' . $delimiter;
            $output .= '----' . $delimiter;
            $output .= '-' . $delimiter;
        }
        return $output;
    }

    private function out($msg, $break = TRUE) {
        if ($this->enable_output) {
            if ((is_array($msg)) || (is_object($msg))) {
                if ($this->cli_mode) {
                    print_r($msg);
                } else {
                    echo('<pre>');
                    print_r($msg);
                    echo('</pre>');
                }
            } else {
                if ($break) {
                    if ($this->cli_mode) {
                        $break_str = PHP_EOL;
                    } else {
                        $break_str = '<br />';
                    }
                } else {
                    $break_str = ' ';
                }
                echo($msg . $break_str);
            }
            if ($this->cli_mode) {

            } else {
                ob_flush();
            }
        }
        
    }

    private function ref($data, $field) {
        //車種
        if ($field == 'CarClass') {
            $key = intval($data[$field]);
            return $this->f_class[$key]->chinese;
        } else if ($field == 'Cripple') {
            //殘障車
            if ($data[$field] == 'Y') {
                return '是';
            } else if ($data[$field] == 'N') {
                return '否';
            }
        } else if ($field == 'Dinning') {
            //餐車
            if ($data[$field] == 'Y') {
                return '是';
            } else if ($data[$field] == 'N') {
                return '否';
            }
        } else if ($field == 'Line') {
            //經由
            if ($data[$field] == '0') {
                return '無';
            } else if ($data[$field] == '1') {
                return '山線';
            } else if ($data[$field] == '2') {
                return '海線';
            }
        } else if ($field == 'LineDir') {
            //行駛方向
            if ($data[$field] == '0') {
                return '順時針';
            } else if ($data[$field] == '1') {
                return '逆時針';
            } else if ($data[$field] == '2') {
                return '海線';
            }
        } else if ($field == 'Note') {
            //備註
            return $data[$field];
        } else if ($field == 'OverNightStn') {
            //跨夜車站
            $key = intval($data[$field]);
            if ($key <> 0) {
                return $this->f_station[$key]->chinese;
            } else {
                return '無';
            }
        } else if ($field == 'Package') {
            //辦理托運
            if ($data[$field] == 'Y') {
                return '是';
            } else if ($data[$field] == 'N') {
                return '否';
            }
        } else if ($field == 'Route') {
            //行駛路線
            return $data[$field];
        } else if ($field == 'Train') {
            //車次
            return $data[$field];
        } else if ($field == 'Type') {
            //狀態
            if ($data[$field] == '0') {
                return '常態';
            } else if ($data[$field] == '1') {
                return '臨時';
            } else if ($data[$field] == '2') {
                return '團體';
            } else if ($data[$field] == '3') {
                return '春節';
            }
        } else if ($field == 'ARRTime') {
            //抵達時間
            return $data[$field];
        } else if ($field == 'DEPTime') {
            //開車時間
            return $data[$field];
        } else if ($field == 'Order') {
            //順序
            return $data[$field];
        } else if ($field == 'Station') {
            //車站
            $key = intval($data[$field]);
            if ($key <> 0) {
                if (isset($this->f_station[$key])) {
                    return $this->f_station[$key]->chinese;
                } else {
                    return $key;
                }
            } else {
                return '無';
            }
        }
    }

    //取得車站或車種資料
    public function get_fixed_data($type) {
        //嘗試取得快取
        $data = $this->get_cache($type);
        if ($data) {
            return $data;
        } else {
            //從原始資料來源取得
            if ($type == 'class') {
                $this->db->select('*');
                $this->db->from('tra_class');
                $this->db->where('class_id IS NOT NULL');
            } else if ($type == 'station') {
                $this->db->select('*');
                $this->db->from('tra_station');
                $this->db->where('station_id IS NOT NULL');
            }
            $query = $this->db->get();
            $data = array();
            if ($type == 'class') {
                foreach ($query->result() as $row) {
                    $data[$row->class_id] = $row;
                }
            } else if ($type == 'station') {
                foreach ($query->result() as $row) {
                    $data[$row->station_id] = $row;
                }
            }

            //快取
            $this->set_cache($type, $data);
            return $data;
        }
    }
    //取得快取內容            
    public function get_cache($type) {
        $filename = $this->cache_dir . $type;
        //檢查檔案在不在      
        if (file_exists($filename)) {  
            //檢查快取過期了沒
            $file_t = filemtime($filename);
            if (!$file_t) {   
                //快取無效    
                $this->flush_cache($filename); 
                return false; 
            } else {
                if ((time() - $file_t) > $this->cache_expire) {
                    //快取過期
                    $this->flush_cache($filename); 
                    return false;                  
                } else {
                    $data = file_get_contents($filename);
                    return unserialize($data);     
                }
            }
        } else {
            return false;
        }
    }  

    //清除快取內容
    public function flush_cache($type) {
        $filename = $this->cache_dir . $type;
        if (file_exists($filename)) {
            return unlink($filename);
        } else {
            return true;
        }
    }

    //設定快取內容
    public function set_cache($type, $data) {
        $filename = $this->cache_dir . $type;
        return file_put_contents($filename, serialize($data));
    }

    //判斷是否已跨日
    private function overnight($time1, $time2) {
        $check = FALSE;
        //比對字元格式
        $ret1 = preg_match('/([0-9]{2}):([0-9]{2}):([0-9]{2})/', $time1, $match1);
        $ret2 = preg_match('/([0-9]{2}):([0-9]{2}):([0-9]{2})/', $time2, $match2);
        if ((isset($match1[0])) && (isset($match2[0]))) {
            //轉成timestamp
            $ts1 = mktime($match1[1], $match1[2], $match1[3]);
            $ts2 = mktime($match2[1], $match2[2], $match2[3]);
            //比對
            if ($ts1 > $ts2) {
                $check = TRUE;
            }
        }
        return $check;
    }

//    public function test2() {
//        return $this->overnight('', '');
//    }

    public function download_timetable($file) {
        //下載檔案
        $url = 'http://163.29.3.98/xml/%s.zip';
        $saveto = '/home/allen/public_html/tra_timetable/files/%s.zip';
//        $filename = date('Ymd', time() + ($days_later * 86400));
        $filename = $file;
        $ch = curl_init(sprintf($url, $filename));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $data = curl_exec($ch);
        curl_close($ch);
        file_put_contents(sprintf($saveto, $filename), $data);
        //解壓縮
        $unzipto = '/home/allen/public_html/tra_timetable/files/';
        system(sprintf('unzip -n -d %s %s%s.zip', $unzipto, $unzipto, $filename));
        //刪除檔案
        unlink(sprintf($saveto, $filename));
    }

    //車種名稱簡化
    private function alt_class($class) {
        $str = trim($class);
        if ($str == '區間車') {
            $ret = '區間';
        } else if ($str == '自強(太魯閣號)') {
            $ret = '太魯閣';
        } else if ($str == 'Tze-Chiang Limited Express') {
            $ret = 'Tze Chiang';
        } else if ($str == 'Chu-Kuang Express') {
            $ret = 'Chu Kuang';
        } else {
            $ret = $str;
        }
        return $ret;
    }

    //傳回有效車站代碼
    public function check_station($check) {
        $return_this = '1008';
        if (is_numeric($check)) {
            //如果是純數字的話用快取查
            if (isset($this->f_station[intval($check)]->chinese)) {
                $return_this = $check;
            }
        } else {
            $fields = array('chinese', 'english');
            foreach($fields as $v) {
                $this->db->flush_cache();
                $this->db->select('station_id');
                $this->db->from('tra_station');
                $this->db->where($v, $check);
                $query = $this->db->get();
                if ($query->num_rows() > 0) {
                    $ret = $query->row();
                    $return_this = (string)$ret->station_id;
                    break;
                }
            }
        }
        return $return_this;

    }

}
