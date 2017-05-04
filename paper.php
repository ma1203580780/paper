<?php

include_once('Paper.class.php');

use Paper\Paper;
//上传到服务器，暂存到本地
//move_uploaded_file($_FILES["file"]["tmp_name"], "upload/" . $_FILES["file"]["name"]);
//
////调用linux下的antiword执行word转文本
//$filename = "./upload/" . $_FILES["file"]["name"];
//$content = shell_exec('antiword '.$filename);
//$content = shell_exec('antiword -mUTF-8 '.$filename);

$result = (new Paper(''))->get();

echo "<pre>";
print_r($result);
?>
<hr>
<html>
    <head>
        <title>word生成题库</title>
        <style type="text/css">
        body,table{
        font-size:16px;
        }
        table{
        table-layout:fixed;
        empty-cells:show;
        border-collapse: collapse;
        margin:0 auto;
        }
        td{
        height:30px;
        }
        h1,h2,h3{
        font-size:16px;
        margin:0;
        padding:0;
        }
        .table{
        border:1px solid #cad9ea;
        color:#666;
        }
        .table th {
        background-repeat:repeat-x;
        height:30px;
        }
        .table td,.table th{
        border:1px solid #cad9ea;
        padding:0 1em 0;
        }
        .table tr.alter{
        background-color:#f5fafe;
        }
        </style>

    </head>
    <body>
    $$x=\frac{-b\pm\sqrt{b^2-4ac}}{2a}$$
        <table width="90%" class="table">
            <tr>
                <td colspan="3">
                大标题：<?php echo $result['title']; ?>
                </td>
            </tr>
            <?php
            for($i=0;$i<(count($result)-1);$i++){
            ?>
            <tr>
                <td colspan="3">
                    大题题干：<?php echo $result[$i]['title']; ?>
                </td>
            </tr>
            <tr>
                <td>类型：    <?php echo $result[$i]['type']; ?></td>
                <td>每题分值： <?php echo $result[$i]['score']; ?></td>
                <td>题量：    <?php echo $result[$i]['amount']; ?></td>
            </tr>
            <tr>
                <?php for($j=0;$j<count($result[$i]['case']);$j++){  ?>

            <tr>
                <td colspan="3">
                    小题题干：<?php echo $result[$i]['case'][$j]['question']; ?>
                </td>
            </tr>
            <tr>
                <td rowspan="<?php echo count($result[$i]['case'][$j]['option'])+1; ?>">
                    选项：
                </td>
            </tr>
                <?php for($z=0;$z<count($result[$i]['case'][$j]['option']);$z++){ ?>
            <tr>
                <td colspan="2">
                    <?php echo $result[$i]['case'][$j]['option'][$z]; ?>
                </td>
            </tr>
                <?php } ?>
            <tr>
                <td>
                    答案：   <?php echo $result[$i]['case'][$j]['answer']; ?>
                </td>
                <td>
                    难易程度：<?php echo $result[$i]['case'][$j]['level']; ?>
                </td>
                <td>
                    解析：   <?php echo $result[$i]['case'][$j]['exp']; ?>
                </td>
            </tr>

                <?php }

            }  ?>

        </table>
    </body>
</html>
<script type="text/javascript" src="https://cdn.mathjax.org/mathjax/latest/MathJax.js?config=TeX-AMS_HTML"></script>







