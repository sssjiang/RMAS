<?php
// Routes

$app->get('/', App\Action\HomeAction::class)
    ->setName('homepage');

// 拉取某篇摘要的hdi results
$app->get('/hdi/results/{id}', function ($request, $response, $args) {
    $docId = $args['id'];

    // 模拟用户信息
    $UserInfo = [
        'nickname' => 'Test User',
        'openid' => 'openid12345'
    ];

    $nickname = isset($UserInfo['nickname']) ? $UserInfo['nickname'] : null;
    $openid = isset($UserInfo['openid']) ? $UserInfo['openid'] : null;

    // 模拟数据
    $mockData = [
        [
            'docId' => $docId,
            'fp' => 'FP123',
            'drug' => json_encode(['Drug A', 'Drug B']),
            'herb' => json_encode(['Herb A', 'Herb B']),
            'species' => json_encode(['Species A', 'Species B']),
            'PKPD' => 'PKPD Info',
            'admin_route' => 'Oral',
            'admin_route_herb' => 'Topical',
            'targets' => json_encode(['Target A', 'Target B']),
            'direction' => 'Positive',
            'interaction' => json_encode(['Interaction A', 'Interaction B']),
            'conclusion' => 'Conclusion text',
            'user' => 'User123'
        ],
        // 添加更多模拟数据项...
    ];

    $result = [
        'api_status' => 'success',
        'code' => 0,
        'content' => [],
    ];

    if ($docId) {
        $items = array_filter($mockData, function($item) use ($docId) {
            return $item['docId'] == $docId;
        });

        foreach ($items as &$item) {
            $item['targets'] = json_decode($item['targets'], true) ?: [];
            $item['drug'] = json_decode($item['drug'], true) ?: [];
            $item['herb'] = json_decode($item['herb'], true) ?: [];
            $item['species'] = json_decode($item['species'], true) ?: [];
            $item['interaction'] = json_decode($item['interaction'], true) ?: [];
        }

        $result['content'] = array_values($items); // 重建索引
    } else {
        $result['api_status'] = 'fail';
        $result['content'] = 'no docId';
    }

    return $response->withJson($result);
});

$app->get('/hdi/references', function ($request, $response, $args) {
    $params = $request->getQueryParams();
    $order_by = isset($params['order_by']) ? $params['order_by'] : '';
    $direction = isset($params['direction']) ? $params['direction'] : 'desc';
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 10;
    $mesh_term = isset($params['mesh_term']) ? $params['mesh_term'] : '';
    $tnum = isset($params['tnum']) ? $params['tnum'] : '0';
    $params['hdi_class'] = isset($params['hdi_class']) ? $params['hdi_class'] : '-1';
    $params['ai_hdi_class'] = isset($params['ai_hdi_class']) ? $params['ai_hdi_class'] : '-1';
    $params['ai_hdi_class_2'] = isset($params['ai_hdi_class_2']) ? $params['ai_hdi_class_2'] : '-1';
    $params['is_50herbs'] = isset($params['is_50herbs']) ? $params['is_50herbs'] : '';
    $tnum = (int) $tnum;

    // 模拟数据
    $items = [
        [
            'id' => 1,
            'source' => 'Source A',
            'dp2_id' => 123,
            'PMID' => 'PMID123456',
            'item' => 'Drug A',
            'ArticleTitle' => 'Title A',
            'ArticleTitle_html' => '',
            'annotation' => json_encode(['connections' => [], 'labels' => []]),
            'MeshHeadingList' => json_encode([['DescriptorName' => 'Mesh A']]),
            'hdi_class' => 'Class A',
            'is_50herbs' => 0,
            'ai_hdi_class' => 'Class B',
            'ai_hdi_class_2' => 'Class C',
            'prob_unrelated' => 0.1,
            'prob_related' => 0.9,
            'has_herb_mesh' => true,
            'last_edited_by' => 'User1',
        ],
        // 其他模拟数据项...
    ];

    $tnum = count($items);
    $items = array_slice($items, $offset, $limit);

    foreach ($items as &$item) {
        if ($item['ArticleTitle_html'] != '') {
            $item['ArticleTitle'] = $item['ArticleTitle_html'];
        }
        unset($item['ArticleTitle_html']);

        $item['mesh'] = [];

        if (isset($item['MeshHeadingList']) && $item['MeshHeadingList'] != '') {
            $meshs = json_decode($item['MeshHeadingList'], true);
            foreach ($meshs as &$mesh) {
                $key = isset($mesh['DescriptorName']) ? $mesh['DescriptorName'] : '';
                if ($key != '') {
                    array_push($item['mesh'], ['mesh' => $key]);
                }
            }
        }

        if (isset($item['annotation'])) {
            $annotation = json_decode($item['annotation'], true);
            $cn = isset($annotation['connections']) ? $annotation['connections'] : [];
            $an = isset($annotation['labels']) ? $annotation['labels'] : [];
            if (is_string($cn)) {
                $cn = json_decode($cn, true);
            }
            $category = '';
            if (is_string($an)) {
                $an = json_decode($an, true);
            }

            $cat = [];
            foreach ($an as &$a) {
                $catId = $a['categoryId'];
                if (isset($cat[$catId])) {
                    $cat[$catId]++;
                } else {
                    $cat[$catId] = 1;
                }
            }
            foreach ($cat as $k => $v) {
                if ($k == 0) {
                    $category .= "Herb=" . $v . ",";
                } elseif ($k == 1) {
                    $category .= "Drug=" . $v . ",";
                } elseif ($k == 2) {
                    $category .= "Species=" . $v . ",";
                } elseif ($k == 3) {
                    $category .= "Conclusion=" . $v . ";";
                }
            }
            $item['annotation'] = 'Conections(' . count($cn) . ");" . "Labels(" . count($an) . "): " . $category;
        }
    }

    $pg = get_pagination($offset, $tnum);

    // 模拟用户信息
    $UserInfo = [
        'headimgurl' => 'https://example.com/user.jpg',
        'name' => 'Test User'
    ];

    $color = "red";

    $table_head = [
        ['id' => 'id', 'name' => '#ID', 'direction' => $order_by == 'id' ? $direction : 'desc', 'color' => $order_by == 'id' ? $color : ''],
        ['id' => 'PMID', 'name' => 'REF_ID', 'direction' => $order_by == 'PMID' ? $direction : 'desc', 'color' => $order_by == 'PMID' ? $color : ''],
        ['id' => 'item', 'name' => 'Drug', 'direction' => $order_by == 'item' ? $direction : 'desc', 'color' => $order_by == 'item' ? $color : ''],
        ['id' => 'ArticleTitle', 'name' => 'Article Title', 'direction' => $order_by == 'ArticleTitle' ? $direction : 'desc', 'color' => $order_by == 'ArticleTitle' ? $color : ''],
        ['id' => 'hdi_class', 'name' => 'Class', 'direction' => $order_by == 'hdi_class' ? $direction : 'desc', 'color' => $order_by == 'hdi_class' ? $color : ''],
        ['id' => 'ai_hdi_class', 'name' => 'CNN Predicted', 'direction' => $order_by == 'ai_hdi_class' ? $direction : 'desc', 'color' => $order_by == 'ai_hdi_class' ? $color : ''],
        ['id' => 'ai_hdi_class_2', 'name' => 'NaiveBayes Predicted', 'direction' => $order_by == 'ai_hdi_class_2' ? $direction : 'desc', 'color' => $order_by == 'ai_hdi_class_2' ? $color : ''],
    ];

    return $this->view->render($response, 'hdi_ref_edit.twig', [
        'items' => $items,
        'params' => $params,
        'tnum' => $tnum,
        'offset' => $offset,
        'pg' => $pg,
        'params' => $params,
        'UserInfo' => $UserInfo,
        'table_head' => $table_head,
        'copyright_year' => date("Y"),
    ]);
});
$app->get('/get/reference/{id}', function ($request, $response, $args) {
    $id = $args['id'];

    // 模拟用户信息
    $UserInfo = [
        'nickname' => 'Test User'
    ];

    $nickname = isset($UserInfo['nickname']) ? $UserInfo['nickname'] : null;

    // 模拟数据
    $mockItem =[
        'source' => 'Source A',
        'PMID' => 'PMID123456',
        'ArticleTitle' => 'Title A',
        'ArticleTitle_html' => '',
        'AbstractText' => 'Abstract A',
        'AbstractText_html' => '',
        'annotation' => json_encode(['labels' => [], 'connections' => []]),
        'annotation_ner' => json_encode(['labels' => []]),
        'species' => 'Species A',
        'herb' => 'Herb A',
        'hdi_class' => 'Class A',
        'last_edited_by' => 'User1',
        'last_edited_at' => '2024-07-01 12:00:00',
        'extracted_by' => '',
        'extracted_at' => '',
        'created_at' => '2024-07-01 12:00:00',
    ];


    $item = $mockItem;

    $labels = [];
    $labels_ner = [];
    if ($item['annotation'] == '') {
        $item['annotation'] = [];
    } else {
        $item['annotation'] = json_decode($item['annotation'], true);
        $labels = safe_json_decode($item['annotation']['labels']);
    }
    if ($item['annotation_ner'] == '') {
        $item['annotation_ner'] = [];
    } else {
        $item['annotation_ner'] = json_decode($item['annotation_ner'], true);
        $labels_ner = safe_json_decode($item['annotation_ner']['labels']);
    }

    // 合并annotation和annotation_ner中的labels, 其中id重新编号
    $new_labels = combine_labels($labels, $labels_ner);
    $item['annotation']['labels'] = json_encode($new_labels, JSON_UNESCAPED_UNICODE);

    if (!isset($item['annotation']['maxWidth'])) {
        $item['annotation']['maxWidth'] = 1000;
    }
    // 抛弃错误的 connections
    if (isset($item['annotation']['connections'])) {
        $label_ids = [];
        foreach ($new_labels as &$label) {
            array_push($label_ids, $label['id']);
        }
        $connections = safe_json_decode($item['annotation']['connections']);
        $new_connections = [];
        foreach ($connections as &$connection) {
            $fromId = $connection['fromId'];
            $toId = $connection['toId'];
            if (in_array($fromId, $label_ids) && in_array($toId, $label_ids)) {
                array_push($new_connections, $connection);
            }
        }
        $item['annotation']['connections'] = json_encode($new_connections, JSON_UNESCAPED_UNICODE);
    }

    if (empty($item['AbstractText'])) {
        $item['AbstractText'] = 'No abstract';
    }
    // 删除 em 标签
    $AbstractText = str_replace('<em>', '', $item['AbstractText']);
    $item['AbstractText'] = str_replace('</em>', '', $AbstractText);
    $ArticleTitle = str_replace('<em>', '', $item['ArticleTitle']);
    $item['ArticleTitle'] = str_replace('</em>', '', $ArticleTitle);

    $item['AbstractText'] .= "\n=======================\n标题：" . $item['ArticleTitle'];

    $is_edited = 0;
    if ($item['extracted_by'] != '' && $item['extracted_by'] != $nickname) {
        $timeFirst = strtotime($item['extracted_at']);
        $timeSecond = strtotime(date("Y-m-d H:i:s"));
        $differenceInSeconds = $timeSecond - $timeFirst;
        if ($differenceInSeconds < 600) {
            $item['AbstractText_html'] = $item['extracted_by'] . ' is editing this doc!';
            $item['ArticleTitle_html'] = $item['extracted_by'] . ' is editing this doc!';
            $is_edited = 1;
        }
    }
    if ($is_edited == 0) {
        // 更新提取人（在模拟数据中不会实际更新）
        $item['extracted_by'] = $nickname;
        $item['extracted_at'] = date("Y-m-d H:i:s");
    }

    $result = [
        'api_status' => 'success',
        'code' => 0,
        'content' => $item,
        'id' => $id,
    ];

    return $response->withJson($result);
});