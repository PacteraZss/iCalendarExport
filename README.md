# iCalendarExport

## 使用方法：
- 1.composer require pactera_zss/icalendar_export  （暂未实现）
- 2.初始化 $iCalendarExport = $new \iCalendarExport($allUser, $allTypes);
- 3.执行导出下载 $iCalendarExport->iCalendarExport($userEmail, $scheduleList);

日程列表数组字段对照：
```php
{
    //日程ID
    "id" => 60000
    //开始时间
    "st_datetime" => "2023-03-27 00:00:00"
    //结束时间
    "ed_datetime" => "2023-03-27 23:55:00"
    //日程类型，会将类型名称前缀到日程标题前
    "type" => 9
    //日程标题
    "subject" => null
    //日程内容
    "comment" => null
    //循环类型 1日重复 2周重复 3月重复 4年重复
    "repeat_kbn" => "0"
    //日：1平日，2日间隔
    //周：0即可
    //月：10 X月X日，X月X周的周X
    "repeat_detail_kbn" => 0
    //间隔
    "repeat_interval" => null
    //循环结束日期
    "repeat_end" => null
    //循环结束次数
    "repeat_count" => null
    //重复的月份，适用于年重复时X月X日
    "repeat_month" => null
    //重复的日期，适用于重复种地X月X日
    "repeat_day" => null
    //week1-7 代表周日至周六的重复周几
    "week1" => null
    "week2" => null
    "week3" => null
    "week4" => null
    "week5" => null
    "week6" => null
    "week7" => null
    //重复的第几周
    "weeks" => null
    //是否开启通知，1为开启
    "notify" => "1"
    //提前通知的分钟数
    "notify_min_ago" => 10
    //是否为一个全天的日程，1是
    "all_day" => 1
    //地点
    "location" => null
    //创建者ID
    "created_user_id" => 115
    //是否公开，0隐私1公开
    "sensitivity" => 1
    //创建时间
    "created_at" => "2023-03-08 13:00:18"
    //更新时间
    "updated_at" => "2023-03-08 13:00:18"
    //删除时间，删除后的日程会将状态导出为取消
    "deleted_at" => null
    //参加者列表，一维数组，参加者的ID
    "schedule_participants" => []
    //子日程列表，字段与主日程一致。
    "schedule_subs" => []
}
```
