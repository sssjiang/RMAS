var options = {
    "indent": "auto",
    "indent-spaces": 2,
    "wrap": 80,
    "markup": true,
    "output-xml": false,
    "numeric-entities": true,
    "quote-marks": true,
    "quote-nbsp": false,
    "show-body-only": true,
    "quote-ampersand": false,
    "break-before-br": true,
    "uppercase-tags": false,
    "uppercase-attributes": false,
    "drop-font-tags": true,
    "tidy-mark": false
};

$(".reset").click(function () {
    $(this).closest('form').find("input[type=text], input[type=hidden], textarea").val("");
    $("#offset").val('0');
    $("#tnum").val('');
    $('#hdi_class').prop('selectedIndex', 0);
    $('#mesh_term').prop('selectedIndex', 0);
    $('#has_herb_mesh').prop('selectedIndex', 0);

    $('#ai_hdi_class').prop('selectedIndex', 0);
    $('#ai_hdi_class_2').prop('selectedIndex', 0);
    $('#last_edited_by').prop('selectedIndex', 0);
    $('#is_50herbs').prop('selectedIndex', 0);
});

$("#btn_redownload").click(function () {
    $("#checkbox_select_all").prop('checked', false);
    $(".cbx_select").each(function (index, value) {
        if (this.checked) {
            var id = $(this).attr("data_id");
            $("#progress_" + id).loading();
            $.ajax({
                url: "/download/manager/reset/" + id, // Url to which the request is send
                type: "get", // Type of request to be send, called as method
                contentType: false, // The content type used when sending data to the server.
                cache: false, // To unable request pages to be cached
                processData: false, // To send DOMDocument or non processed data file it is set to false
                success: (data) => // A function to be called if request succeeds
                {
                    $("#progress_" + id).loading('stop');
                    if (data.api_status === 'success') {
                        $("#progress_" + id).text('9');
                        $("#cbx_" + id).prop('checked', false);
                    }
                }
            });
        }
    });
});

$("#btn_insert_redis").click(function () {
    $.ajax({
        url: "/download/manager/insert/redis", // Url to which the request is send
        type: "get", // Type of request to be send, called as method
        contentType: false, // The content type used when sending data to the server.
        cache: false, // To unable request pages to be cached
        processData: false, // To send DOMDocument or non processed data file it is set to false
        success: (data) => // A function to be called if request succeeds
        {
            if (data.api_status === 'success') {
                alert(JSON.stringify(data.content, null, 2));
            }
        }
    });
});

$("#btn_redis_check").click(function () {
    var list = $("#list").val();
    var id = $("#redis_id").val();
    $.ajax({
        url: "/get/api/redis/" + list + "/" + id, // Url to which the request is send
        type: "get", // Type of request to be send, called as method
        contentType: false, // The content type used when sending data to the server.
        cache: false, // To unable request pages to be cached
        processData: false, // To send DOMDocument or non processed data file it is set to false
        success: (data) => // A function to be called if request succeeds
        {
            if (data.api_status === 'success') {
                $("#show_redis").val(JSON.stringify(data, null, 2));
            }
        }
    });
});

$("#btn_check_redis").click(function () {
    $("#redis_check").modal('show');
});

$("#btn_add_task").click(function () {
    $("#myModal").modal('show');
    reload('new');
});

$("#checkbox_select_all").click(function () {
    $(".cbx_select").prop('checked', this.checked);
});

$("#btn_beautify").click(function () {
    $("#show_html").val(tidy_html5($("#show_html").val(), options));
});



$("#btn_show").click(function () {
    console.log(JSON.stringify(window.annotator.store.connectionRepo.json));
    console.log(JSON.stringify(window.annotator.store.labelRepo));
});

// 隐藏ner标签
$("#btn_hide_ner").click(function () {
    if (typeof (window.annotator) !== 'undefined') {
        const btn_text = $("#btn_hide_ner").html();
        if (btn_text == 'HideNER') {
            // 获取当前所有的label，排除 id>20的
            const labels = window.annotator.store.labelRepo.json;
            let labelsDeleted = [];
            labels.forEach((item, idx) => {
                // console.log(item, window.annotator.store.labelRepo);
                if (item.categoryId > 20) {
                    // 删除这个label
                    //window.annotator.store.labelRepo.delete(item.labelId);
                    window.annotator.applyAction(Poplar.Action.Label.Delete(item.id));
                    labelsDeleted.push(item);
                }
            });
            window.annotator.store.labelsDeleted = labelsDeleted;
            $("#btn_hide_ner").html('ShowNER');
        } else {
            // 显示隐藏的NER labels
            const labelsDeleted = window.annotator.store.labelsDeleted;
            if (typeof (labelsDeleted) !== 'undefined') {
                labelsDeleted.forEach((item, idx) => {
                    window.annotator.applyAction(Poplar.Action.Label.Create(item.categoryId, item.startIndex, item.endIndex));
                });
                window.annotator.store.labelsDeleted = [];
            }
            $("#btn_hide_ner").html('HideNER');
        }
    }
});

// 更新 id 对应的下载参数
$("#btn_save").click(function () {
    var id = $("#Modal_Show").attr("data_id");
    if (typeof (id) === 'undefined') {
        id = $("#modal_show_id").attr("data_id");
    }
    // var title = $('#title_html').froalaEditor('html.get');
    // var abstract = $('#abstract_html').froalaEditor('html.get');
    var hdi_class = $('#m_hdi_class').val();
    // var species = $('#species').val();
    // var herb = $('#herb').val();


    const connections = JSON.stringify(window.annotator.store.connectionRepo.json);
    const labels = JSON.stringify(window.annotator.store.labelRepo.json);
    const synonyms = JSON.stringify(window.synonyms);
    const json = {
        'connections': connections,
        'labels': labels,
        'synonyms': synonyms,
        'maxWidth': getSvgWdith()
    };
    // if (hdi_class == 0) {
    //   alert('Please set Class');
    //   return 0;
    // } else {
    json.hdi_class = hdi_class;
    // }
    console.log(JSON.stringify(json, null, 2));
    $("#btn_loading").removeClass("hide");
    $.ajax({
        url: "/hdi/reference/update/" + id,
        type: "post",
        dataType: 'json',
        data: json,
        success: (data) => {
            $("#btn_loading").addClass("hide");
            if (data.api_status === 'success') {
                //$("#Modal_Show").modal('hide');
                // 更新检索结果列表上的Title
                //$("#title_" + id).html(title);
                $("#class_" + id).html('<font color="red">' + hdi_class + '</font>');
                $("#row_" + id).css('background-color', '#e6cba9');
            }
        }
    });
});

const btn_delete_hdi_row = (id) => {
    $("#hdi_result_" + id).remove();
    // 删除服务器端数据
    $("#btn_loading").removeClass("hide");
    $.ajax({
        url: "/hdi/item/delete/" + id,
        type: "post",
        dataType: 'json',
        success: (data) => {
            $("#btn_loading").addClass("hide");
            if (data.api_status === 'success') {
                console.log(data);
            } else {
                alert("Save to database failed! Please retry.");
            }
        }
    });
};

const btn_edit_hdi_row = (id) => {
    const data = $("#hdi_result_" + id).data("data");
    const payload = JSON.parse(decodeURIComponent(data));
    console.log(payload);
    $("#keys_drug").val(payload.drug);
    $("#keys_drug").selectpicker("refresh");
    $("#keys_herb").val(payload.herb);
    $("#keys_herb").selectpicker("refresh");
    $("#keys_object").val(payload.targets);
    $("#keys_object").selectpicker("refresh");
    $("#keys_species").val(payload.species);
    $("#keys_species").selectpicker("refresh");
    $("#keys_conclusion").val(payload.conclusion);
    $("#keys_conclusion").selectpicker("refresh");
    $("#keys_direction").val(payload.direction);
    $("#keys_interaction").val(payload.interaction);
    $("#keys_interaction").selectpicker("refresh");
    $("#keys_pk_pd").val(payload.PKPD);
    $("#keys_admin_route").val(payload.admin_route);
    $("#keys_admin_route_herb").val(payload.admin_route_herb);
};

const render_hdi_table = (data) => {
    data.map((item, idx) => {
        const fp = item.fp;
        // 更新 fp对应的行
        if ($("#hdi_result_" + fp).length) {
            $("#hdi_result_" + fp).remove();
        }
        const rowJson = encodeURIComponent(JSON.stringify(item));
        const hdi_row = "<tr id='hdi_result_" + fp + "' data-data='" + rowJson + "'><td>"
            + "<button type='button' class='btn btn-sm btn-default' onclick='btn_edit_hdi_row(\"" + fp + "\")'>Edit</button><button type='button' class='btn btn-sm btn-danger' onclick='btn_delete_hdi_row(\"" + fp + "\")'>Delete</button></td><td>"
            + item.drug.join(';') + "</td><td>"
            + item.herb.join(';') + "</td><td>"
            + item.species.join(';') + "</td><td>"
            + item.admin_route + "</td><td>"
            + item.admin_route_herb + "</td><td>"
            + item.PKPD + "</td><td>"
            + item.targets.join(';') + "</td><td>"
            + item.direction + "</td><td>"
            + item.interaction.join(';') + "</td><td>"
            + "<span title='" + item.conclusion + "'>DETAIL</span></td><td>"
            + item.user + "</td></tr>";
        $("#tb_content").append(hdi_row);
    });
};

const reset_hdi_inputs = () => {
    $("#keys_drug").empty();
    $("#keys_drug").selectpicker("refresh");

    $("#keys_herb").empty();
    $("#keys_herb").selectpicker("refresh");

    const default_species = [
        'Human',
        'Patient',
        'Healthy Volunteers',
        'Male',
        'Female',
        'Rat',
        'Mice',
        'Dog',
        'Rabbit',
        'Cells',
        'Enzymes',
        'In-Vitro'
    ];
    $("#keys_species").empty();
    default_species.map((item) => {
        $("#keys_species").append('<option value="' + item + '">' + item + '</option>');
    });
    $("#keys_species").selectpicker('refresh');

    $("#keys_object").empty();
    $("#keys_object").selectpicker("refresh");

    $("#keys_direction").val('');
    $("#keys_interaction").val('');
    $("#keys_interaction").selectpicker("refresh");
    $("#keys_conclusion").empty();
    $("#keys_conclusion").selectpicker("refresh");
    $("#keys_pk_pd").val('');
    $("#keys_admin_route").val('');
    $("#keys_admin_route_herb").val('');
    $("#tb_content").empty();
};

// 保存 keys_*
$("#btn_save_keys").click(function () {
    var id = $("#Modal_Show").attr("data_id");
    if (typeof (id) === 'undefined') {
        id = $("#modal_show_id").attr("data_id");
    }
    if (!$("#keys_drug").val()) {
        alert("please select a DRUG");
        return false;
    }
    if (!$("#keys_herb").val()) {
        alert("please select a HERB");
        return false;
    }
    if (!$("#keys_herb").val()) {
        alert("please select a HERB");
        return false;
    }
    if (!$("#keys_object").val()) {
        alert("please select Diseases/PK Parameters/Targets");
        return false;
    }
    if (!$("#keys_species").val()) {
        alert("please select a StudyTypes");
        return false;
    }
    if (!$("#keys_direction").val()) {
        alert("please select Direction");
        return false;
    }
    if (!$("#keys_interaction").val()) {
        alert("please select an Interaction Type");
        return false;
    }
    if (!$("#keys_conclusion").val()) {
        alert("please input a conclusion");
        return false;
    }
    if (!$("#keys_pk_pd").val()) {
        alert("please select PK/PD");
        return false;
    }
    if (!$("#keys_admin_route").val()) {
        alert("please select Administration Route");
        return false;
    }

    // 设定Class
    // $("#m_hdi_class").val('1');

    const keys_drug = $("#keys_drug").val().sort();
    const keys_herb = $("#keys_herb").val().sort();
    const keys_object = $("#keys_object").val();
    const keys_species = $("#keys_species").val();
    const keys_conclusion = $("#keys_conclusion").val();
    const keys_direction = $("#keys_direction").val();
    const keys_interaction = $("#keys_interaction").val();
    const keys_pk_pd = $("#keys_pk_pd").val();
    const keys_admin_route = $("#keys_admin_route").val();
    const keys_admin_route_herb = $("#keys_admin_route_herb").val();


    const fp = md5(id + JSON.stringify(keys_drug) + JSON.stringify(keys_herb) + keys_pk_pd + keys_admin_route + JSON.stringify(keys_object) + keys_direction);

    const UserInfo = JSON.parse($.cookie('UserInfo') !== undefined ? $.cookie('UserInfo') : '{}');
    const payload = [{
        'docId': id,
        'fp': fp,
        'drug': keys_drug,
        'herb': keys_herb,
        'species': keys_species,
        'PKPD': keys_pk_pd,
        'admin_route': keys_admin_route,
        'admin_route_herb': keys_admin_route_herb,
        'targets': keys_object,
        'direction': keys_direction,
        'interaction': keys_interaction,
        'conclusion': keys_conclusion,
        'user': UserInfo.nickname,
    }];

    render_hdi_table(payload);
    $("#btn_save").click();

    $("#btn_loading").removeClass("hide");
    $.ajax({
        url: "/hdi/item/add",
        type: "post",
        dataType: 'json',
        data: payload[0],
        success: (data) => {
            $("#btn_loading").addClass("hide");
            if (data.api_status === 'success') {
                console.log(data);
            } else {
                alert("Save to database failed! Please retry.");
            }
        }
    });
});

const toFullName = (item) => {
    const synonyms = window.synonyms;
    if (typeof (synonyms[item]) !== 'undefined') {
        return synonyms[item];
    } else {
        return item;
    }
};

const removeDuplicate = (items) => {
    const newItems = [];
    const newKeys = {};
    var i;
    for (i = 0; i < items.length; i++) {
        if (!newKeys.hasOwnProperty(items[i].value)) {
            newKeys[items[i].value] = 1;
            newItems.push(items[i]);
        }
    }
    return newItems;
}

// 用当前最新的 labels 更新 keys 控件上的内容
$("#btn_save_test").click(function () {
    const id = $("#Modal_Show").attr("data_id");
    const labels = window.annotator.store.labelRepo.json;
    const abstract = window.annotator.store._content;
    const labelCategories = window.annotator.store.labelCategoryRepo.json;

    const categories = {};
    labelCategories.map((labCat) => {
        categories[labCat.id] = labCat.text;
    });

    //$("#keys_conclusion").val('');
    const herbs = [];
    const drugs = [];
    const species = [];
    const targets = [];
    const conclusions = [];
    labels.map((label, idx) => {
        //console.log(label);
        const categoryId = label.categoryId;
        const startIndex = label.startIndex;
        const endIndex = label.endIndex;
        let optionTitle;
        const itemOrig = abstract.substring(startIndex, endIndex);
        const item = toFullName(itemOrig);
        if (item === itemOrig) {
            optionTitle = item;
        } else {
            optionTitle = `${item}(${itemOrig})`;
        }
        const option = categories[categoryId];

        if (categoryId === 0) {
            herbs.push({
                value: item,
                title: optionTitle
            });
        } else if (categoryId === 1) {
            drugs.push({
                value: item,
                title: optionTitle
            });
        } else if (categoryId === 2) {
            species.push({
                value: item,
                title: optionTitle
            });
        } else if (categoryId === 3) {
            // $("#keys_conclusion").val(item);
            conclusions.push({
                value: item,
                title: item,
            });
        } else if (categoryId === 4) {
            targets.push({
                value: item,
                title: optionTitle
            });
        }

    });
    let uniqueHerbs = removeDuplicate(herbs);
    let uniqueDrugs = removeDuplicate(drugs);
    let uniqueSpecies = removeDuplicate(species);
    let uniqueTargets = removeDuplicate(targets);
    let uniqueConclusions = removeDuplicate(conclusions);

    console.log('conclusion', labels, uniqueConclusions);

    if (uniqueHerbs.length) {
        const keys_herb = $("#keys_herb").val();
        $("#keys_herb").empty();
        uniqueHerbs.map((item) => {
            $("#keys_herb").append('<option value="' + item.value + '">' + item.title + '</option>');
        });
        $("#keys_herb").selectpicker('refresh');
        console.log("selected items", keys_herb);
        $("#keys_herb").val(keys_herb);
        $("#keys_herb").selectpicker('refresh');
    }
    if (uniqueDrugs.length) {
        const keys_drug = $("#keys_drug").val();
        $("#keys_drug").empty();
        uniqueDrugs.map((item) => {
            $("#keys_drug").append('<option value="' + item.value + '">' + item.title + '</option>');
        });
        $("#keys_drug").selectpicker('refresh');
        console.log("selected items", keys_drug);
        $("#keys_drug").val(keys_drug);
        $("#keys_drug").selectpicker('refresh');
    }
    if (uniqueSpecies.length) {
        const keys_species = $("#keys_species").val();
        $("#keys_species").empty();
        uniqueSpecies.map((item) => {
            $("#keys_species").append('<option value="' + item.value + '">' + item.title + '</option>');
        });
        $("#keys_species").selectpicker('refresh');
        $("#keys_species").val(keys_species);
        $("#keys_species").selectpicker('refresh');
    }
    if (uniqueTargets.length) {
        const keys_object = $("#keys_object").val();
        $("#keys_object").empty();
        uniqueTargets.map((item) => {
            $("#keys_object").append('<option value="' + item.value + '">' + item.title + '</option>');
        });
        $("#keys_object").selectpicker('refresh');
        $("#keys_object").val(keys_object);
        $("#keys_object").selectpicker('refresh');
    }
    if (uniqueConclusions.length) {
        const keys_conclusion = $("#keys_conclusion").val();
        $("#keys_conclusion").empty();
        uniqueConclusions.map((item) => {
            $("#keys_conclusion").append('<option value="' + item.value + '">' + item.title + '</option>');
        });
        $("#keys_conclusion").selectpicker('refresh');
        $("#keys_conclusion").val(keys_conclusion);
        $("#keys_conclusion").selectpicker('refresh');
    }
});

// 保存选择的标签
$("#btn_category_save").click(function () {
    // 检查是否要更新 window.synonyms
    const startIndex = $("#tmpValue1").val();
    const endIndex = $("#tmpValue2").val();
    const AbstractText = window.annotator.store._content;
    // 获得已选择的单词
    const item = AbstractText.substring(startIndex, endIndex);
    const selected_item = $("#selected_item").val();
    if (item !== selected_item) {
        window.synonyms[item] = selected_item;
    }
    var radioValue = $("input[name='optradio']:checked").val();
    if (radioValue) {
        $("#selection_category").modal('hide');
        const idx = parseInt(radioValue, 10);
        console.log("selected Category " + radioValue, $("#tmpValue1").val(), $("#tmpValue2").val())
        window.annotator.applyAction(Poplar.Action.Label.Create(idx, $("#tmpValue1").val(), $("#tmpValue2").val()));
        updateSvgWidth();
        $("#btn_save_test").click();
    } else {
        alert('no category selected!');
    }
});

const copyToClipboard = (str) => {
    $("#selected_item").focus();
    $("#selected_item").select();
    const successful = document.execCommand('copy');
};

// 将选择的内容copy to clipboard
$("#btn_copy_selected").click(function () {
    copyToClipboard();
});

// 将选择的词全部应用到整片摘要
$("#btn_category_apply_to_all").click(function () {
    var radioValue = $("input[name='optradio']:checked").val();
    if (radioValue) {
        $("#selection_category").modal('hide');
        const idx = parseInt(radioValue, 10);
        console.log("selected Category " + radioValue, $("#tmpValue1").val(), $("#tmpValue2").val())
        const AbstractText = window.annotator.store._content;
        // 获得已选择的单词
        const item = AbstractText.substring($("#tmpValue1").val(), $("#tmpValue2").val());
        const itemLen = item.length;
        let startIndex = 0;
        let endIndex = 0;
        if (itemLen > 0) {
            while ((index = AbstractText.indexOf(item, endIndex)) > -1) {
                startIndex = index;
                endIndex = index + itemLen;
                window.annotator.applyAction(Poplar.Action.Label.Create(idx, startIndex, endIndex));
            }
            updateSvgWidth();
            $("#btn_save_test").click();
        }
    } else {
        alert('no category selected!');
    }
});

var removeContainerChild = () => {
    var div = document.getElementById("container");
    while (div.hasChildNodes()) {
        div.removeChild(div.firstChild);
    }
    window.synonyms = {};
};

// 根据rect标签的最大长度，重新设定SVG的width
const updateSvgWidth = (width) => {
    if (typeof (width) === 'undefined' || width === 1000) {
        width = getSvgWdith();
    }
    setSvgWdith(width);
    if (width > 1000) {
        $("#svg_container").css('overflow-x', 'auto');
    } else {
        $("#svg_container").css('overflow-x', 'hidden');
    }
};

const setSvgWdith = (width) => {
    width = width + 300;
    const height = $("svg")[0].clientHeight;
    const style = "height: " + height + "px; width: " + width + "px;";
    $("svg")[0].setAttribute('style', style);
};

const getSvgWdith = () => {
    let maxWidth = 1000;
    $('rect').each(function (idx, item) {
        const width = parseInt(item.getAttribute('width'), 10);
        if (width > maxWidth) {
            maxWidth = width;
        }
    });
    return maxWidth;
};

var initAnnotator = (options) => {
    $("#btn_hide_ner").html('HideNER');

    const labelCategories = options.labelCategories;
    let html = '';
    for (let i = 0; i < labelCategories.length; i++) {
        html += "<div class='radio'><label><input type='radio' name='optradio' value='" + i + "'>" + labelCategories[i].text + "</label></div>";
    }
    $("#category_options").html(html);
    updateCategoryRadio();

    const connectionCategories = options.connectionCategories;
    html = '';
    for (let i = 0; i < connectionCategories.length; i++) {
        html += "<div class='radio'><label><input type='radio' name='conradio' value='" + i + "'>" + connectionCategories[i].text + "</label></div>";
    }
    $("#connection_options").html(html);
    updateConnectionRadio();

    // if (window.annotator) {
    //     window.annotator.remove();
    //     delete window.annotator;
    // }
    // removeContainerChild();

    let annotator = new Poplar.Annotator(JSON.stringify(options), document.getElementById("container"));

    annotator.on('textSelected', (startIndex, endIndex) => {
        console.log("select", startIndex, endIndex);
        $("#selection_category").modal('show');
        $("#tmpValue1").val(startIndex);
        $("#tmpValue2").val(endIndex);
        const AbstractText = annotator.store._content;
        // 获得已选择的单词
        const item = AbstractText.substring($("#tmpValue1").val(), $("#tmpValue2").val());
        $("#selected_item").val(item);
    });
    annotator.on('labelClicked', (labelId) => {
        console.log(labelId + "clicked");
    });
    annotator.on('twoLabelsClicked', (fromLabelId, toLabelId) => {
        console.log("connect", fromLabelId, toLabelId);

        // fromLabelId和toLabelId是否有效
        if (VerifyLableId(fromLabelId) && VerifyLableId(toLabelId)) {
            $("#selection_connection").modal('show');
            $("#tmpValue1").val(fromLabelId);
            $("#tmpValue2").val(toLabelId);
        } else {
            alert("Wrong fromLabelId (" + fromLabelId + ") and toLabelId (" + toLabelId + ")");
        }
        //annotator.applyAction(Poplar.Action.Connection.Create(0, fromLabelId, toLabelId));
    });
    annotator.on('labelRightClicked', (labelId, event) => {
        console.log('labelRightClicked', labelId)
        labelId = parseInt(labelId, 10);
        const labels = window.annotator.store.labelRepo.json;
        let valid = false;
        labels.forEach((item, idx) => {
            if (item.id === labelId && item.categoryId > 20) {
                // 创建新的label
                valid = true;
                const categoryId = item.categoryId - 20;
                annotator.applyAction(Poplar.Action.Label.Create(categoryId, item.startIndex, item.endIndex));
            }
        });
        annotator.applyAction(Poplar.Action.Label.Delete(labelId));
    });
    annotator.on('connectionRightClicked', (connectionId, event) => {
        annotator.applyAction(Poplar.Action.Connection.Delete(connectionId));
    });
    annotator.on('contentInput', (position, value) => {
        annotator.applyAction(Poplar.Action.Content.Splice(position, 0, value));
    });
    annotator.on('contentDelete', (position, length) => {
        annotator.applyAction(Poplar.Action.Content.Splice(position, length, ""));
    });
    window.annotator = annotator;

    setTimeout(() => {
        updateSvgWidth();
        console.log("updateSvgWidth");
    }, 500);
};

// 检查该label是否NER识别的
var isNerLabel = (labelId) => {
    labelId = parseInt(labelId, 10);
    const labels = window.annotator.store.labelRepo.json;
    let valid = false;
    labels.forEach((item, idx) => {
        if (item.id === labelId && item.categoryId > 20) {
            valid = true;
        }
    });
    return valid;
};

var VerifyLableId = (labelId) => {
    labelId = parseInt(labelId, 10);
    const labels = window.annotator.store.labelRepo.json;
    let valid = false;
    labels.forEach((item, idx) => {
        // 禁止与NER预测的标签建立关联
        if (item.id === labelId && item.categoryId < 20) {
            valid = true;
        }
    });
    return valid;
};

var updateCategoryRadio = () => {
    // $("input[type='radio']").click(function() {
    //     var radioValue = $("input[name='optradio']:checked").val();
    //     if (radioValue) {
    //         $("#selection_category").modal('hide');
    //         const idx = parseInt(radioValue, 10);
    //         console.log("selected Category " + radioValue, $("#tmpValue1").val(), $("#tmpValue2").val())
    //         window.annotator.applyAction(Poplar.Action.Label.Create(idx, $("#tmpValue1").val(), $("#tmpValue2").val()));
    //         updateSvgWidth();
    //     }
    // });
};

var updateConnectionRadio = () => {
    $("input[type='radio']").click(function () {
        var radioValue = $("input[name='conradio']:checked").val();
        if (radioValue) {
            $("#selection_connection").modal('hide');
            const idx = parseInt(radioValue, 10);
            console.log("selected Connection " + radioValue, $("#tmpValue1").val(), $("#tmpValue2").val());
            const fromLabelId = parseInt($("#tmpValue1").val(), 10);
            const toLabelId = parseInt($("#tmpValue2").val(), 10);
            if (VerifyLableId(fromLabelId) && VerifyLableId(toLabelId)) {
                window.annotator.applyAction(Poplar.Action.Connection.Create(idx, fromLabelId, toLabelId));
            }
        }
    });
};

var goToPage = (pg) => {
    var offset = (pg - 1) * 10;
    $("#offset").val(offset);
    if (pg == 1) {
        $("#tnum").val('');
    }
    $("#download_pool").submit();
};

var labqr_logout = () => {
    $.removeCookie("unionid", {
        path: '/',
        domain: '.labqr.com'
    });
    $.removeCookie("UserInfo", {
        path: '/',
        domain: '.labqr.com'
    });
    location.reload();
};

var set_unrelated = (id, hdi_class) => {
    var json = {
        'hdi_class': hdi_class
    };

    $("#down_table").loading();
    $.ajax({
        url: "/hdi/reference/update/" + id,
        type: "post",
        dataType: 'json',
        data: json,
        success: (data) => {
            $("#down_table").loading('stop');
            if (data.api_status === 'success') {
                // 更新检索结果列表上的Title
                $("#class_" + id).html('<font color="red">' + hdi_class + '</font>');
            }
        }
    });
};

var showModel = (id) => {
    $("#title_html").empty();
    $("#abstract_html").empty();
    $("#species").val('');
    $("#herb").val('');
    $('#m_hdi_class').prop('selectedIndex', 0);
    $("#Modal_Show").attr("data_id", id);
    $("#m_PMID").html("DocID: " + id);

    $("#Modal_Show").modal('show');
    $("#btn_loading").removeClass("hide");

    reset_hdi_inputs();

    // Load hdi results
    $.ajax({
        url: "/hdi/results/" + id,
        type: "get",
        contentType: false,
        cache: false,
        processData: false,
        success: (data) => {
            if (data.api_status === 'success') {
                //console.log(data.content);
                render_hdi_table(data.content);
            } else {
                alert(data.content);
            }
        }
    });

    // 载入摘要信息
    $.ajax({
        url: "/get/reference/" + id, // Url to which the request is send
        type: "get", // Type of request to be send, called as method
        contentType: false, // The content type used when sending data to the server.
        cache: false, // To unable request pages to be cached
        processData: false, // To send DOMDocument or non processed data file it is set to false
        success: (data) => // A function to be called if request succeeds
        {
            //$('#loading').hide();
            //console.log(data);

            if (data.api_status === 'success') {
                //$("#Modal_Show").modal('show');

                // $("#species").val(data.content.species);
                // $("#herb").val(data.content.herb);
                $('#m_hdi_class').prop('selectedIndex', parseInt(data.content.hdi_class, 10));
                $("#modal_last_edited_by").html('Last Edited By: ' + data.content.last_edited_by + '(' + data.content.last_edited_at + ')');

                $("#m_PMID").html("DocID: " + id + '(' + data.content.created_at + ')');

                // $('#title_html').froalaEditor('html.set', data.content.ArticleTitle_html);
                // $('#title_html').froalaEditor('events.trigger', 'charCounter.count');
                // $('#abstract_html').froalaEditor('html.set', data.content.AbstractText_html);
                // $('#abstract_html').froalaEditor('events.trigger', 'charCounter.count');

                $('#artical_title').html(data.content.ArticleTitle);

                const annotation = data.content.annotation;
                const labels = typeof (annotation.labels) !== 'undefined' ? JSON.parse(annotation.labels) : [];
                const connections = typeof (annotation.connections) !== 'undefined' ? JSON.parse(annotation.connections) : [];
                const maxWidth = typeof (annotation.maxWidth) !== 'undefined' ? annotation.maxWidth : 1000;

                // 本摘要中代号与全程的对照表
                const synonyms = typeof (annotation.synonyms) !== 'undefined' ? annotation.synonyms : {};

                const options = {
                    "content": data.content.AbstractText,
                    "synonyms": synonyms,
                    "labelCategories": [{
                        "id": 0,
                        "text": "Herb/herbal component",
                        "color": "#eac0a2",
                        "borderColor": "#8c7361"
                    },
                    {
                        "id": 1,
                        "text": "Western Drug",
                        "color": "#7ed321",
                        "borderColor": "#f8e71c"
                    },
                    {
                        "id": 2,
                        "text": "Species (in vitro, rat, mice or human)",
                        "color": "#1cf8dc",
                        "borderColor": "#f8991c"
                    },
                    {
                        "id": 3,
                        "text": "HDI/DDI Conclusion",
                        "color": "#d279c3",
                        "borderColor": "#417505"
                    },
                    {
                        "id": 4,
                        "text": "Diseases/PK Parameters/Targets",
                        "color": "#86b0ed",
                        "borderColor": "#d0021b"
                    },
                    {
                        "id": 5,
                        "text": "unknown",
                        "color": "#86b0ed",
                        "borderColor": "#d0021b"
                    },
                    {
                        "id": 21,
                        "text": "WesternDrug(机器标记)",
                        "color": "#fffb8f",
                        "borderColor": "#9013fe"
                    }, {
                        "id": 211,
                        "text": "HeZhiAng",
                        "color": "#fffb8f",
                        "borderColor": "#9013fe"
                    }
                    ],
                    "labels": labels,
                    "connections": connections,
                    "connectionCategories": [{
                        "id": 0,
                        "text": "PK(A↓)"
                    }, {
                        "id": 1,
                        "text": "PK(A↑)"
                    },
                    {
                        "id": 2,
                        "text": "PK(D↓)"
                    }, {
                        "id": 3,
                        "text": "PK(D↑)"
                    }, {
                        "id": 4,
                        "text": "PK(M↓)"
                    }, {
                        "id": 5,
                        "text": "PK(M↑)"
                    }, {
                        "id": 6,
                        "text": "PK(E↓)"
                    }, {
                        "id": 7,
                        "text": "PK(E↑)"
                    }, {
                        "id": 8,
                        "text": "PD(↓)"
                    }, {
                        "id": 9,
                        "text": "PD(↑)"
                    }, {
                        "id": 10,
                        "text": "Synonym of"
                    }, {
                        'id': 11,
                        "text": "Abbreviation of"
                    }, {
                        'id': 12,
                        "text": "Metabolite of"
                    }
                    ]
                };
                // console.log('options', JSON.stringify(options, null, 2));
                removeContainerChild();
                setTimeout(() => {
                    $("#btn_loading").addClass("hide");
                    initAnnotator(options);
                    window.synonyms = synonyms;
                    console.log("initAnnotator");
                    $("#btn_save_test").click();
                    $("#btn_hide_ner").click();
                }, 500);

            } else {
                alert(data.content);
            }
        }
    });
};