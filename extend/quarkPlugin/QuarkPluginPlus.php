<?php

namespace quarkPlugin;

use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Request;
use think\Exception;
use app\model\Source as SourceModel;
use app\model\SourceLog as SourceLogModel;

class QuarkPluginPlus
{
    protected $url;
    protected $model;
    protected $SourceLogModel;

    public function __construct()
    {
        //
//        $this->url = "https://pan.xinyuedh.com";
        $this->SourceLogModel = new SourceLogModel();
        $this->model = new SourceModel();
//        $this->source_category_id = 0;
    }

    /**
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws DbException
     */
    public function getAllShareLink()
    {
        @set_time_limit(999999);

        // 分页获取分享链接
        $page_no = 1;
        $dataList = '';
        $allData = [];
        $cookies = Config('qfshop.quark_cookie') ?? '';
        while ($dataList == '' || !empty($dataList)) {
            $queryParams = array(
                'pr' => 'ucpro',
                'fr' => 'pc',
                'uc_param_str' => '',
                '_page' => $page_no,
                '_size' => 1000,
                '_order_field' => 'created_at',
                '_order_type' => 'asc',
                '_fetch_total' => 1,
                '_fetch_notify_follow' => '1'
            );
            $res = curlHelper("https://drive-pc.quark.cn/1/clouddrive/share/mypage/detail", "GET", '', [], $queryParams, $cookies)['body'];
            $res = json_decode($res, true);

            $page_no++;

            if ($res['code'] == 0) {
                $dataList = $res['data']['list'];
                // 筛选出 status 不为 1 的数据
                $filteredDataList = array_filter($dataList, function ($item) {
                    return $item['status'] !== 1;
                });
                // 提取 share_url
                $shareUrls = array_column($filteredDataList, 'share_url');

                // 删除失效的链接
                $this->deleteSourceUrlIn($shareUrls);

                // 提取 有效的title
                $filteredDataList = array_filter($dataList, function ($item) {
                    return $item['status'] == 1;
                });
                // 过滤出标题不在数据库中的数据
                $notExistDataList = $this -> selectSourceTitleNotIn($filteredDataList);
                // 不在数据库中的数据则需要批量入库
                $this -> insertSourceBatch($notExistDataList);
            }
            // 可以添加一个最大重试次数的限制，防止无限循环
            if ($page_no > 1000) {
                break;
            }

        }

        return ;
//        $this->processDataConcurrently($allData, $logId, $dataList['total_result']);
//
//        $this->SourceLogModel->editLog($logId, $dataList['total_result'], '', '', 3);
    }

    protected function insertSourceBatch($quarkDataList = [])
    {
        foreach ($quarkDataList as $key => $quarkData) {
            if(empty($quarkData['title'])){
                $patterns = '/^\d+\./';
                $title = preg_replace($patterns, '', $quarkData['title']);
            }else{
                $title = $quarkData['title'];
            }
            $source_category_id = 0;

            // 添加资源到系统中
            $data["title"] = $title;
            $data["url"] = $quarkData['share_url'];
            $data["is_type"] = determineIsType($data["url"]);
            $data["code"] = $quarkData['code']??'';
            $data["source_category_id"] = $source_category_id;
            $data["update_time"] = time();
            $data["create_time"] = time();
            $data["fid"] = $quarkData['first_fid']??'';
            $dataList[] = $data;
        }
        // 使用 Db 类的 insertAll 方法批量插入数据
        if (empty($dataList)){
            return ;
        }
        $this -> model ->insertAll($dataList);
    }

    /**
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws DbException
     */
    protected function selectSourceTitleNotIn($source_list=[])
    {
        if (empty($source_list)){
            return [];
        }
        $source_title_list = array_column($source_list, 'title');

        // 查询支援表中
        $result = $this -> model
            ->whereIn('title',$source_title_list)
            ->field('title');
        $result =$result->select()->toArray();
        if (empty($result) || count($result)==0){
            return $source_list;
        }
        // 提取查询结果中的 title
        $result_titles = array_column($result, 'title');

        // 计算差集，即 source_title_list 中不在 result_titles 中的标题
//        $result_titles = array_diff($source_title_list, $result_titles);

        // 返回差集
        return array_values(array_filter($source_list, function ($item) use ($result_titles) {
            return !in_array($item['title'], $result_titles);
        }));
    }

    /**
     * @throws DbException
     */
    protected function deleteSourceUrlIn($url_ist)
    {
        if (empty($url_ist)){
            return ;
        }
        $this -> model
            ->where('url', 'in', $url_ist)
            ->delete();
    }
}
