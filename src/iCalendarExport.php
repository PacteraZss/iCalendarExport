<?php
/**
 * iCalendarExport
 */
namespace pacteraZss\icalendarExport;

class iCalendarExport{

    // 用户列表。格式：  {[id：XXXX, email:XXXX, name: XXXX]}
    private $allUser = [];
    //日程类型。格式：  id：XXXX name:XXXX
    private $allTypes = [];
    //无标题时填充标题
    private $emptyTitle = '無題';
    //当前时间。格式：Y-m-d H:i:s
    private $datetime = "";
    //换行符
    private $eol = PHP_EOL;
    //文本换行符
    private $textEol = '\n';
    //验证环境：例如：STG->开发环境,DEV=>生产环境, 或空
    private $PRODID = "";
    //时区前缀，默认日本
    private $TZID = "TZID=Asia/Tokyo:";

    //子日程临时列表
    private $sub_list = [];

    /**
     * @param array $allUser 用户列表 格式：  {[id：XXXX, email:XXXX, name: XXXX]}
     * @param array $allTypes 日程类型列表 格式 {[id：XXXX, name: XXXX]}
     * @param string $emptyTitle 无标题时填充标题
     * @param string $datetime 留空自动获取当前时间，格式：Y-m-d H:i:s
     * @param string $eol 换行符 默认PHP_EOL
     * @param string $textEol 文本换行符， 默认 \n
     * @param string $TZID 时区前缀，默认日本 格式：TZID=Asia/Tokyo:
     * @param string $PRODID 验证环境：例如：STG->开发环境,DEV=>生产环境, 或空
     */
    public function __construct($allUser, $allTypes, $emptyTitle = '', $datetime = '', $eol = '', $textEol = '', $TZID = '', $PRODID = '')
    {
        $this->allUser = $allUser;
        $this->allTypes = $allTypes;
        $this->datetime = empty($datetime) ? date('Y-m-d H:i:s') : $datetime;
        if(!empty($emptyTitle)) $this->emptyTitle = $emptyTitle;
        if(!empty($eol)) $this->eol = $eol;
        if(!empty($textEol)) $this->textEol = $textEol;
        if(!empty($TZID)) $this->TZID = $TZID;
        if(!empty($PRODID)) $this->PRODID = $PRODID;
    }


    /**
     * 导出 iCalendar 的ics文件
     * @param string $userEmail 用户电子邮箱
     * @param array $scheduleList 日程列表
     * @param string $fileName 文件名称，无需后缀[.ics]
     * @return void
     */
    public function iCalendarExport($userEmail, $scheduleList, $fileName = 'iCalendar'){

        //共通データの初期化
        $this->initData();
        $file_name = "$fileName.ics";
        $this->echoHeader($file_name, $userEmail);

        foreach ($scheduleList as $schedule){
            $this->echoSchedule($schedule);
        }
        $this->echoListAndEol([], true);
    }

    /**
     * INIT DATA
     * @return void
     */
    private function initData(){
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $this->getAllUser();
        $this->getAllFacility();
        $this->getAllTypes();
        $this->datetime = $this->getTZIDJapan(now());
        $this->PRODID = env('ICAL_PRODID', "");
    }

    /**
     * @param $list
     * @return void
     */
    private function echoListAndEol($list, $end = false){
        $line_max = 75;
        if(count($list) > 0){
            foreach ($list as $key => $row){
                if(!empty($row)){
                    $row_len = mb_strlen($row, 'utf8');
                    if($row_len > $line_max){
                        $row = $this->mbWordwrap($row, $line_max, $this->eol, true);
//                        $row = wordwrap($row, $line_max-1, $this->eol.' ', true);
                        echo $row;
                        echo $this->eol;
                    } else {
                        echo $row;
                        echo $this->eol;
                    }
                }
            }
        }
        if($end){
            echo "END:VCALENDAR".$this->eol;
        }
    }

    /**
     * @param  string $str
     * @param int $width
     * @param string $break
     * @param false $cut
     * @return String
     * @see
     */
    public function mbWordwrap(string $str, int $width = 10, string $break = "\n", bool $cut = false) : String
    {
        $lines = explode($break, $str);
        // filter ATTENDEE
        $line_prefix = mb_substr($str, 0, 8) == 'ATTENDEE' ? '' : ' ';
        $line_key = 0;
        foreach ($lines as &$line) {
            $line = rtrim($line);

            $line_width = $line_key > 0 ? $width - 1: $width;

            if (mb_strlen($line) <= $line_width) {
                $line = $line_prefix.$line;
                continue;
            }
            $words = explode($line_prefix, $line);
            $line = '';
            $actual = '';
            foreach ($words as $k => $vo_word) {

                $line_width = $line_key > 0 ? $width - 1: $width;

                $vo_arr = explode("\n", $vo_word);

                foreach ($vo_arr as $key => $word){

                    if (mb_strlen($actual.$word) <= $line_width) {
                        $actual .= $word;
                    } else {
                        if ($actual != '') {
                            $line .= $line_prefix.rtrim($actual).$break;
                            $line_key++;
                        }
                        $actual = $word;
                        if ($cut) {
                            while (mb_strlen($actual) > $line_width) {

                                $line_width = $line_key > 0 ? $width - 1: $width;

                                $line .= $line_prefix.mb_substr($actual, 0, $line_width).$break;
                                $actual = mb_substr($actual, $line_width);
                                $line_key++;
                            }
                        }
                    }
                    if(count($vo_arr) == 1){
                        if(strpos($vo_word, "\n")) $actual .= $this->text_eol;
                    } else {
                        if($key == 0 && mb_strpos($vo_word, "\n") == mb_strlen($word) - 1) $actual .= $this->text_eol;
                        if($key > 0 && $key < count($vo_arr) - 1) $actual .= $this->text_eol;
                        if($key == count($vo_arr) - 1 && mb_strpos($vo_word, "\n", -1) == mb_strlen($vo_word) - 1) $actual .= $this->text_eol;
                    }
                    $actual .= ' ';
                }
            }
            $line .= $line_prefix.trim($actual);
        }
        return trim(implode($break, $lines));
    }
    /**
     * @param $file_name
     * @return void
     */
    private function echoHeader(string $file_name = 'test.ics', $user_email = ''){
        //出力ファイル
        header('Content-Type: text/Calendar');
        header('Content-Disposition: inline; filename='.$file_name);
        $top_list = [
            "BEGIN:VCALENDAR",
            "PRODID:".$this->getProdid(),
            "VERSION:2.0",
            "CALSCALE:GREGORIAN",
            "METHOD:PUBLISH",
            "X-WR-CALNAME:".$user_email,
            "X-WR-TIMEZONE:".config('app.timezone'),
        ];
        $this->echoListAndEol($top_list);
    }

    /**
     * Echo Schedule
     * @param $schedule
     * @return void
     */
    private function echoSchedule($schedule, $user_id){
        $echo_list = [
            'BEGIN:VEVENT',
            'DTSTART:'.$this->getTZIDJapan($schedule['st_datetime']),
            'DTEND:'.$this->getTZIDJapan($schedule['ed_datetime']),
            'DTSTAMP:'.$this->datetime,
            //LOCATION:
            $this->getLocation($schedule['location'], []),
            //SEQUENCE
            'SEQUENCE:'.(!empty($schedule['updated_at']) ? strtotime($schedule['updated_at']) : strtotime($schedule['created_at'])),
            //UID
            $this->getUid($schedule['st_datetime'], $schedule['id'], 0, $schedule['created_user_id']),
            'CREATED:'.$this->getTZIDJapan($schedule['created_at']),
            //ORGANIZER
            $this->getOrganizer($schedule['created_user_id']),
            //ATTENDEE
            $this->getAttendee(isset($schedule['schedule_participants']) ? $schedule['schedule_participants'] : []),
            'LAST-MODIFIED:'.$this->getTZIDJapan($schedule['updated_at']),
            $this->getCategories($schedule['type'], true),
            //  'STATUS:'.(empty($schedule['deleted_at']) ? 'CONFIRMED' : 'CANCELLED'),
            'SUMMARY:'.$this->getSummary($schedule['type'], $schedule['subject']),
            'DESCRIPTION:'.$schedule['comment'].$this->getDescriptionParticipants(isset($schedule['schedule_participants']) ? $schedule['schedule_participants'] : []),
            $this->getClass($schedule['sensitivity']),
        ];

        if($schedule['repeat_kbn'] > 0 && (!isset($schedule['schedule_subs']) || count($schedule['schedule_subs']) == 0)){
            $echo_list[] = 'STATUS:CANCELLED';
        } else {
            $echo_list[] = 'STATUS:'.(empty($schedule['deleted_at']) ? 'CONFIRMED' : 'CANCELLED');
        }

        if($schedule['notify'] == 1){
            $echo_list[] = 'BEGIN:VALARM';
            $echo_list[] = 'ACTION:DISPLAY';
            $echo_list[] = 'DESCRIPTION:'.$this->getSummary($schedule['type'], $schedule['subject']);
            $echo_list[] = "TRIGGER:-PT{$schedule['notify_min_ago']}M";
            $echo_list[] = 'END:VALARM';
        }
        if($schedule['repeat_kbn'] > 0){
            $repeat = $this->getRepeatInfo($schedule, $user_id);
            if($repeat['RRULE'])  $echo_list[] = $repeat['RRULE'];
            if($repeat['EXDATE'] && count($repeat['EXDATE']) > 0) {
                foreach ($repeat['EXDATE'] as $exdate){
                    $echo_list[] = $exdate;
                }
            }
            if(count($repeat['TIME']) == 2) {
                $echo_list[1] = 'DTSTART:'.$this->getTZIDJapan($repeat['TIME'][0]);
                $echo_list[2] = 'DTEND:'.$this->getTZIDJapan($repeat['TIME'][1]);
            }
        }

        $echo_list[] = 'END:VEVENT';
        $this->echoListAndEol($echo_list);

        if(count($this->sub_list) == 0) return true;

        foreach ($this->sub_list as $sub){
            $echo_list = [
                'BEGIN:VEVENT',
                'DTSTART:'.$this->getTZIDJapan($sub['start_at']),
                'DTEND:'.$this->getTZIDJapan($sub['end_at']),
                'DTSTAMP:'.$this->datetime,
                $this->getLocation($sub['location'], $sub['schedule_facility'] ?: []),
                'SEQUENCE:'.(!empty($sub['updated_at']) ? strtotime($sub['updated_at']) : strtotime($sub['created_at'])),
//                $this->getUid($schedule['st_datetime'], $schedule['id'], 0, $schedule['created_user_id']),
                $this->getUid($sub['start_at'], $sub['id'], 0, $sub['created_user_id']?:$schedule['created_user_id']),
                'CREATED:'.$this->getTZIDJapan($sub['created_at']),
                $this->getOrganizer($sub['created_user_id']?:$schedule['created_user_id']),
                $this->getAttendee($sub['schedule_participants'] ?: []),
                'LAST-MODIFIED:'.$this->getTZIDJapan($sub['updated_at']?:$schedule['updated_at']),
                $this->getCategories($sub['type'], true),
                'SUMMARY:'.$this->getSummary($sub['type'], $sub['subject']),
                'DESCRIPTION:'.$sub['comment'].$this->getDescriptionParticipants($sub['schedule_participants']?: []),
                $this->getClass($sub['sensitivity']),
            ];

            if($schedule['notify'] == 1){
                $echo_list[] = 'BEGIN:VALARM';
                $echo_list[] = 'ACTION:DISPLAY';
                $echo_list[] = 'DESCRIPTION:'.$this->getSummary($sub['type'], $sub['subject']);
                $echo_list[] = "TRIGGER:-PT{$sub['notify_min_ago']}M";
                $echo_list[] = 'END:VALARM';
            }
            $echo_list[] = 'END:VEVENT';
            $this->echoListAndEol($echo_list);
        }
        $this->sub_list = [];
    }

    /**
     * 繰り返し INFO
     * @param $schedule
     * @return array
     */
    private function getRepeatInfo($schedule, $user_id){
        //RRULE EXDATE
        $return['RRULE'] = null;
        $return['EXDATE'] = null;
        $return['TIME'] = [];

        $repeat = [];
        $repeat_common = [];
        $repeat_common['WKST'] = 'MO';
        if($schedule['repeat_end']){
            $repeat_common['UNTIL'] = substr($this->getTZIDJapan($schedule['repeat_end']), 0, 8);
            $repeat_common['UNTIL'] .= 'T';
            $repeat_common['UNTIL'] .= substr($this->getTZIDJapan($schedule['ed_datetime']), -6);
        } else if($schedule['repeat_count']){
            $repeat_common['COUNT'] = $schedule['repeat_count'];
        }
        $repeat_common['INTERVAL'] = $schedule['repeat_interval'];


        switch ($schedule['repeat_kbn']) {
            case '1':  // 日繰り返し
                if($schedule['repeat_detail_kbn'] == 1){ //平日
                    $repeat['FREQ'] = 'WEEKLY';

                    $repeat = array_merge($repeat, $repeat_common);

                    $repeat['BYDAY'] = 'MO,TU,WE,TH,FR';
                } else if($schedule['repeat_detail_kbn'] == 2){ //日間隔
                    $repeat['FREQ'] = 'DAILY';

                    $repeat = array_merge($repeat, $repeat_common);

                }
                break;
            case '2': // 週繰り返し
                $repeat['FREQ'] = 'WEEKLY';

                $repeat = array_merge($repeat, $repeat_common);

                $week = $this->getWeek($schedule);
                $repeat['BYDAY'] = implode(',', $week);
                break;
            case '3': // 月繰り返し
                $repeat['FREQ'] = 'MONTHLY';

                $repeat = array_merge($repeat, $repeat_common);

                if($schedule['repeat_detail_kbn'] == 10){ //月間隔＋日
                    $repeat['BYMONTHDAY'] = $schedule['repeat_day'];
                } else if($schedule['repeat_detail_kbn'] == 2){ //月間隔＋第〇週＋曜日
                    $week = $this->getWeek($schedule);
                    if(count($week) == 1){
                        $week = $week[0];
                        $repeat['BYDAY'] = $schedule['weeks'] > 4 ? '-1'.$week : '+'.$schedule['weeks'].$week;
                    } else {
                        return $return;
                    }
                }
                break;
            case '4': // 年繰り返し
                $repeat['FREQ'] = 'YEARLY';

                $repeat = array_merge($repeat, $repeat_common);

                $repeat['BYMONTH'] = $schedule['repeat_month'];
                if($schedule['repeat_detail_kbn'] == 2){ //年間隔＋月＋日
                    $repeat['BYMONTHDAY'] = $schedule['repeat_day'];
                } else if ($schedule['repeat_detail_kbn'] == 14){ //年間隔+月+第〇週＋曜日
                    $week = $this->getWeek($schedule);
                    if(count($week) == 1){
                        $week = $week[0];
                        $repeat['BYDAY'] = $schedule['weeks'] > 4 ? '-1'.$week : '+'.$schedule['weeks'].$week;
                    } else {
                        return $return;
                    }
                }
                break;
        }
        $repeat_info = [];
        foreach ($repeat as $key => $vo){
            $repeat_info[] = $key.'='.$vo;
        }
        $return['RRULE'] = 'RRULE:'.implode(';', $repeat_info);

        if(!isset($schedule['schedule_subs']) || count($schedule['schedule_subs']) == 0){
            return $return;
        }

        $start_datetime = $schedule['st_datetime'];
        $end_datetime = null;
        $exdate = [];
        $sub_key = 0;

        $sub_date_arr = [];
        foreach ($schedule['schedule_subs'] as  $sub){
            $sub_date = substr($sub['start_at'], 0, 10);
            if(in_array($sub_date, $sub_date_arr)) continue;
            $sub_exists = ScheduleParticipant::where('schedule_id',$schedule['id'])
                ->where('schedulesub_id',$sub['id'])
                ->where('user_id', $user_id)
                ->whereNull('deleted_at')
                ->exists();
            if(!empty($sub['deleted_at'])){
                $exdate[] = 'EXDATE;'.$this->getTZIDJapan($sub['start_at'], true);
            } else if(!$sub_exists){
                $exdate[] = 'EXDATE;'.$this->getTZIDJapan($sub['start_at'], true);
            } else if(!empty($sub['outlook_cal_u_id'])){
                $exdate[] = 'EXDATE;'.$this->getTZIDJapan($sub['start_at'], true);
                $this->sub_list[] = $sub;
            } else {
                if($sub_key == 0) {
                    $start_datetime = $sub['start_at'];
                    $end_datetime = $sub['end_at'];
                    $sub_key++;
                }
            }
            $sub_date_arr[] = $sub_date;

//            else {
//                if($sub['end_at']  < $start_datetime){
//                    $start_datetime = $sub['start_at'];
//                    $end_datetime = $sub['end_at'];
//                }
//            }
        }

        if(count($exdate) > 0){
            $return['EXDATE'] = $exdate;
        }
        if($end_datetime) $return['TIME'] = [$start_datetime, $end_datetime];

        return $return;
    }

    /**
     * @param $schedule
     * @return array
     */
    private function getWeek($schedule){
        $week = [];
        //SU、MO、TU、WE、TH、FR、SA
        if($schedule['week1'] == 1) $week[] = 'SU';
        if($schedule['week2'] == 1) $week[] = 'MO';
        if($schedule['week3'] == 1) $week[] = 'TU';
        if($schedule['week4'] == 1) $week[] = 'WE';
        if($schedule['week5'] == 1) $week[] = 'TH';
        if($schedule['week6'] == 1) $week[] = 'FR';
        if($schedule['week7'] == 1) $week[] = 'SA';
        return $week;
    }

    /**
     * @param $sensitivity
     * @return string
     */
    private function getClass($sensitivity){
//        return 'CLASS:PUBLIC';
        return $sensitivity == 0 ? 'CLASS:PRIVATE' : 'CLASS:PUBLIC';
    }

    /**
     * @param $type_id
     * @param $subject
     * @return string
     */
    private function getSummary($type_id, $subject){
        $summary = '';
        if($type_name = $this->getCategories($type_id)){
            $summary .= "【{$type_name}】";
        }
        if(!empty($subject)){
            $summary .= $subject;
        } else {
            $summary .= $this->emptyTitle;
        }

        return $summary;
    }

    /**
     * @param $type_id
     * @return string|null
     */
    private function getCategories($type_id, $prefix = false){
        $CATEGORIES = 'CATEGORIES:';
        if(isset($this->all_types[$type_id])) {
            if($prefix){
                return $CATEGORIES.$this->all_types[$type_id];
            } else {
                return $this->all_types[$type_id];
            }
        }
        return null;
    }

    /**
     * @param $participants
     * @return string
     */
    private function getAttendee($participants){
        $attendee = 'ATTENDEE;CUTYPE=INDIVIDUAL;';
        if(count($participants) == 0) return $attendee;

        $user_list = [];
        foreach ($participants as $user_id){
            if(isset($this->all_user[$user_id])){
                $user_list[] = $attendee."CN={$this->all_user[$user_id]['name']}:mailto:{$this->all_user[$user_id]['email']}";
            }
        }
        if(count($user_list) > 0){
            return implode($this->eol, $user_list);
        } else {
            return null;
        }
    }

    /**
     * @param $participants
     * @return string
     */
    private function getDescriptionParticipants($participants){
        $return = ' ';
        if(!is_array($participants) || count($participants) == 0) return $return;
        $must = $arbitrarily = [];
        foreach ($participants as $participant){
            $user = isset($this->all_user[$participant['user_id']]) ? $this->all_user[$participant['user_id']] : false;
            if(!$user) continue;
            //・ユーザ名　＜メールアドレス＞
            if($participant['require_flag'] == 0){
                $arbitrarily[] = "・{$user['name']}＜{$user['email']}＞ ";
            } else {
                $must[] = "・{$user['name']}＜{$user['email']}＞ ";
            }
        }


        if(count($must) > 0){
            $return .= $this->text_eol.' <参加者> '.$this->text_eol.implode($this->text_eol, $must);
        }
        if(count($arbitrarily) > 0){
            $return .= $this->text_eol.' ＜任意＞ '.$this->text_eol.implode($this->text_eol, $arbitrarily);
        }
        return $return;
    }

    /**
     * @param $uid
     * @return string
     */
    private function getOrganizer($uid){
        if(isset($this->all_user[$uid])){
            $user_info = $this->all_user[$uid];
        } else {
            return '';
        }

        return "ORGANIZER;CN={$user_info['name']}:mailto:".$user_info['email'];
    }

    /**
     * @return string
     */
    private function getProdid(){
        $prodid = '-//TechnoCrea//OneCale 1.0.0';
        if($this->PRODID == 'DEV'){
            return $prodid.'/DEV/';
        }
        if($this->PRODID == 'STG'){
            return $prodid.'/STG/';
        }
        return $prodid;
    }

    /**
     * @param $st_datetime
     * @param $schedule_id
     * @param $sub_id
     * @param $uid
     * @return string
     */
    private function getUid($st_datetime, $schedule_id, $sub_id, $uid){
        if(isset($this->all_user[$uid])){
            $user_info = $this->all_user[$uid];
        } else {
            return '';
        }

        if($this->PRODID == 'DEV'){
            $email = '@dev.onecale.com';
        }else if($this->PRODID == 'STG'){
            $email = '@stg.onecale.com';
        } else {
            $email = '@onecale.com';
        }

        $uid_info = [
            date('YmdHis', strtotime($st_datetime)),
            $user_info['enterprise_id'],
            $user_info['id'],
            $schedule_id,
            $sub_id
        ];

        return 'UID:'.implode('-', $uid_info).$email;
    }

    /**
     * @param $schedule_location
     * @param $schedule_facility
     * @return string
     */
    private function getLocation($schedule_location, $schedule_facility){
        $location = $schedule_location;
        if(is_array($schedule_facility) && count($schedule_facility) > 0){
            $facility_name = [];
            foreach ($schedule_facility as $s_facility){
                if(isset($this->all_facility[$s_facility['facility_id']])){
                    $facility_name[] = $this->all_facility[$s_facility['facility_id']];
                }
            }
            if(count($facility_name) > 0) $location .= '/'.implode('/', $facility_name);
        }

        return 'LOCATION:'.$location;
    }

    /**
     * 更新回数
     * @param $deleted_at
     * @param $created_at
     * @param $updated_at
     * @return int
     */
    private function getSequence($deleted_at, $created_at, $updated_at){
        if(!empty($deleted_at)) return 'SEQUENCE:2';
        if($created_at != $updated_at){
            return 'SEQUENCE:1';
        } else {
            return 'SEQUENCE:0';
        }
    }

    /**
     * Get TZID Japan
     * @param $datetime
     * @return string
     */
    private function getTZIDJapan($datetime, $TZID = false, $utc = false){
        $utc_timestamp = $utc ? time()- strtotime(gmdate('Y-m-d H:i:s')) : 0;
        $prefix = $TZID ? $this->TZID : '';
        return $prefix.date('Ymd\THis', strtotime($datetime) - $utc_timestamp);
    }

    /**
     * Get All User
     * @return array
     */
    private function getAllUser(){
        $all_user = User::whereNull('deleted_at')
            ->select(['id', 'enterprise_id', 'name', 'email'])
            ->get()->toArray();
        $user_list = [];
        foreach ($all_user as $user){
            $user_list[$user['id']] = $user;
        }
        unset($all_user);
        $this->all_user = $user_list;
    }

    /**
     * Get All Facility
     * @return array
     */
    private function getAllFacility(){
        $all_facilit = Facility::whereNull('deleted_at')->pluck('name', 'id')->toArray();
        $this->all_facility = $all_facilit;
    }

    /**
     * Get All Types
     * @return array
     */
    private function getAllTypes(){
        $all_types = Types::whereNull('deleted_at')->pluck('name', 'id')->toArray();
        $this->all_types = $all_types;
    }
}