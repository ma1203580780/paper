<?php

namespace  Paper;


class Paper{

    public $result;             //结果数组
    public $errors;             //错误数组
    public $content;            //基础文本内容
    public $title;              //大标题
    public $questionName;       //大题题目
    public $questionParts;      //大题的全部小题
    private $opt;               //答案的选项指针

    function __construct($content = '',$showRules = false){
        ini_set("pcre.backtrack_limit",1000000);
        $this->content       = empty($content)?$this->showSample():$content;
        $this->content       = $this->tolerant($this->content);
        $this->title         = $this->getTitle($this->content);
        $this->questionName  = $this->getQuestionName($this->content);
        $this->questionParts = $this->getParts($this->content);
        $this->opt           = $this->ConfigOpt();
        $this->result        = [];
        $this->errors        = [];
        //是否显示规则
        if($showRules)  echo self::rules();

    }

    /**
     * 获取结果
     * @return array|mixed
     */
    public function get()
    {
        //大标题
        $result['title'] = $this->title;
        //遍历大题，获得各大题的小题信息
        foreach($this->questionParts as $keys => $values){
            //题干
            $problems=$this->getProblems($values);
            //选项及正确答案
            $opts = $this->getOpts($values);
            //解析部分
            $exp = $this->getExps($values);
            //组装小题数据
            $qBlocks = [];
            foreach($problems as $k=>$v){
                //题干
                $qBlocks[$k]['question'] = $v;
                //选项
                $qBlocks[$k]['option']   = $opts['options'][$k];
                //答案
                $qBlocks[$k]['answer']   = $opts['trueStr'][$k];
                //解析
                $qBlocks[$k]['exp']      = $exp['exp'][$k];
                //难易程度
                $qBlocks[$k]['level']    = $exp['levels'][$k];
            }
            $qMaster = $this->questionInfo($this->questionName[$keys]);
            //将大题的主体信息和包含的小题整块的信息合并
            $result[$keys] = array_merge($qMaster,['case'=>$qBlocks]);
        }
        //对结果数组进行整理
        $result = $this->trimArray($result);
//        $result = 111;
        return $result;
    }


    /**
     *  每道小题的题干
     * @param $values
     * @return mixed
     */
    public function getProblems($values){
        $problems = [];
        preg_match_all("/\[##\](.*?)[\r\n]/s",  $values, $problems);
        return $problems[1];
    }


    /**
     * 每道小题的选项及正确答案
     * @param $values
     * @return array
     */
    public function getExps($values){
        $exp = [];
        preg_match_all("/\[exp-begin\](.*?)\[exp-end\]/s",  $values, $exp);
    //    echo "<pre>"; var_dump($exp);die;
        //每道小题的等级（难易程度）
        $levels = [];
        foreach ($exp[1] as $e=>$r){
            //匹配level
            $level = [];
            preg_match_all("/\[\@(.*?)\@\]/s",$r, $level);
            $levels[$e]=empty($level[1][0])?'':$level[1][0];
            //清洗exp的内容
            $exp[1][$e] = str_replace(array('[@难@]','[@中@]','[@易@]'), '', $r);
        }
        return ['exp'=>$exp[1],'levels'=>$levels];
    }


    /**
     * 获得小题的选项及正确答案
     * @param $values
     * @return array
     */
    public function getOpts($values){
        $answer = [];
        preg_match_all("/\[opt-begin\](.*?)\[opt-end\]/s",  $values, $answer);
        //遍历一道大题的所有小题的选项，再将选项分隔开
        $options = [];
        foreach ($answer[1] as $key=>$value){
            $options[$key] = explode("||",$value);
            //获取小题的正确答案，选项以逗号分隔
            $true = [];
            foreach ($options[$key] as $i => $o){
                if($this->isInString($o,'✔') || $this->isInString($o,'√')){
                    //当选项内含有对勾时，赋值给answer数组
                    $true[]= $this->opt[$i];
                    //清洗掉选项中的标志正确答案的标记
                    $options[$key][$i] = str_replace(array("✔",'√'," "), '', $o);
                }
            }
            //大题中所有小题答案的键值
            $trueStr[] = implode(',',$true);

        }
        return ['options'=>$options,'trueStr'=>$trueStr];
    }


    /**
     * 获得每个大题的全部小题
     * @param $content
     * @return array
     */
    public function getParts($content){
        preg_match_all("/\[q-begin\](.*?)\[q-end\]/s",  $content, $questionParts);
        return empty($questionParts[1])?[]:$questionParts[1];
    }

    /**
     * 获得大题信息
     * @param $questionName
     * @return mixed
     */
    public function questionInfo($questionName){
        //大题题目
        $info['title'] = str_replace(array("\r\n", "\r", "[*","*]","[!","!]","&nbsp;"), "", $questionName);
        //大题的类型（单选，多选，判断）
        $radioFlag    = $this->isInString($questionName,'单选') || $this->isInString($questionName,'单项选择');
        $checkboxFlag = $this->isInString($questionName,'多选') || $this->isInString($questionName,'多项选择');
        $judgeFlag    = $this->isInString($questionName,'判断');
        $info['type'] = $radioFlag?'radio':($checkboxFlag?'checkbox':($judgeFlag?'judge':'unknown'));
        //大题的每题的分数
        preg_match_all("/\[\!(.*?)\!\]/s",  $questionName, $score);
        $info['score'] = $score[1][0];
        //大题的题量
        preg_match_all("/\[\*(.*?)\*\]/s",  $questionName, $amount);
        $info['amount'] =$amount[1][0];
        return $info;
    }



    /**
     * 获得大标题(一个word试卷只有一个大标题)
     * @param $content
     * @return string
     */
    public function getTitle($content){
        preg_match_all("/(.*?)\[title\]/s", $content,$title);
        if(empty($title[1][0])) return $error[]='title is null!';
        return trim($title[1][0]);
    }

    /**
     * 获得大题题目
     * @param $content
     * @return array
     */
    public function getQuestionName($content){
        preg_match_all("/\[q-begin\](.*?)[\r\n]/s", $content,$questionName);
        return empty($questionName[1])?[]:$questionName[1];
    }


    /**
     * 中英文 字符容错
     * @param $content
     * @return bool|mixed
     */
    public function tolerant($content){
        if(empty($content)) return false;
        $content = str_replace('！', '!', $content);
        $content = str_replace("．", '.', $content);
        $content = str_replace('　', ' ', $content);
        return $content;
    }

    /**
     * 清除数组中所有字符串两端空格
     * @param $Input
     * @return array|mixed
     */
    public function trimArray($Input){
        if (!is_array($Input))
            return  trim(str_replace(array("\r\n", "\r", "\n"), "", $Input));
        return array_map(array($this, 'trimArray'), $Input);
    }


    /**
     * 查找字符串中是否包含某些字符
     * @param $haystack
     * @param $needle
     * @return bool
     */
    public function isInString($haystack, $needle) {
        $array = explode($needle, $haystack);
        return count($array) > 1;
    }


    /**
     * 将XML转为array
     * @param $xml
     * @return mixed
     */
    public static function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);   //禁止引用外部xml实体
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }


    /**
     * 样例
     * @return string
     */
    public function showSample(){
        $content ="四川教师资格考试真题（教育学2）[title]
    [q-begin]一、单项选择题（本大题共[*5*]小题，每小题[!2!]分，共10分）
     [##]1．在教育的基本要素国，作为中介的是（  ）
    [opt-begin]A．教育者√  || 
      B.受教育者   ||
      C.教育影响  ||
      D.教育内容  || 
     E.的士速递
    [opt-end]
    [exp-begin] 111 [@难@][exp-end]
    
    [##]2．远古教育具有原始性，不属于其特征的是（  ）
    [opt-begin]A．非独立性 ||  B.贫乏性    ||   C.无阶级性  ||  D.等级性√[opt-end]
    [exp-begin] 122 [exp-end]
    
     [##]3．教育需要由浅入深，由简单到复杂、由具体到抽象，由低级到高级，这是因为学生的身心发展具有（  ）
    [opt-begin]A．顺序性√ ||   B.阶级性   ||   C.不平衡性 ||   D.个别差异性[opt-end]
    [exp-begin] 这里录入答案解释 [@中@][exp-end]
    
     [##]4．教师最突出的角色是（  ）
    [opt-begin]A．教员√   ||    B.领导者   ||   C.父母代言人 || D.心理医疗者[opt-end]
    [exp-begin] 134 [@中@][exp-end]
    
     [##]5．思维发展处于由具体形象思维向抽象逻辑思维过渡，具体形象思维仍然起重要作用的阶段是（  ）
     [opt-begin]A．小学  ||     B.初中√    ||     C.高中     ||   D.大学[opt-end]
    [exp-begin] 156 [@易@][exp-end]
    
    [q-end]
    
    [q-begin]二、多项选择题（本大题共[*3*]小题，每小题[!10!]分，共30分）
    [##]6．教师的职业态度和职业情感应该包括（  ）
    [opt-begin]A．非独立性 ||  B.贫乏性√    ||   C.无阶级性√  ||  D.等级性√ ||  E.与学生打成一片[opt-end]
    [exp-begin] 121 [@易@][exp-end]
    [##]7．教师劳动具有复杂性，这是因为（  ）
    [opt-begin]A．非独立性 ||  B.贫乏性    ||   C.无阶级性  ||  D.等级性√ || E.体力劳动过程[opt-end] 
    [exp-begin] 131 [@易@][exp-end]
    [##]8．家庭教育的特点有（  ）
    [opt-begin]A．非独立性 ||  B.贫乏性    ||   C.无阶级性  ||  D.等级性√ || E．教育者对受教育者了解和影响具有深刻性√[opt-end]
    [exp-begin] 141 [@易@][exp-end]
    [##]9．请选择你会做的题（  ）
    [opt-begin]A．English Chinese Translation  ||  B.文言文写小说   ||   C.翻跟头√  [opt-end]
    [exp-begin] 看你怎么选择了，加油！ [@易@] [exp-end]

    [q-end]
    
    [q-begin]三、判断题（本大题共[*2*]小题，每小题[!10!]分，共20分）
    [##]10．教师的职业态度和职业情感应该包括（  ）
    [opt-begin]A．是 ||  B.否√   [opt-end]
    [exp-begin] 211 [@难@][exp-end]
    [##]11．教师劳动具有复杂性，这是因为（  ）
    [opt-begin]A．是√ ||  B.否   [opt-end]
    [exp-begin] 311 [@易@][exp-end]
    [q-end]
    ";
        return $content;
    }

    /**
     * 规则提示
     * @return string
     */
    public static function rules(){
        $rules = " 
    <h1>word读取题库 应用说明</h1>
    <ul>
        <li> 1.试卷的大标题有且只能有一个，在标题同一行末尾加上'[title]'标记，不可回车换行。</li>
        <li> 2.每道大题，用'[q-begin]'和'[q-end]'包括。</li>
        <li> 3.'[q-begin]'位于大题题干在同行的起始位置，'[q-end]'可以换行也可以在同一行，并无要求。</li>
        <li> 4.大题题干中，共 x 小题处，x 用'[*'和'*]'包裹；每小题 y 分处，y 用 '[!' 和 '!]' 包裹；例如 \"本大题共[*5*]小题，每小题[!2!]分，共10分\"。</li>
        <li> 5.'[##]'位于小题题干同一行首部。</li>
        <li> 6.'[opt-begin]'，'[opt-end]'包裹小题所有选项，选项之间用 \"||\"分隔。</li>
        <li> 7.大题的题干在'多选'，'多项选择'，'单选'，'单项选择'，'判断'中，必要包含且只能包含一个，标识大题的类型。</li>
        <li> 8.'[exp-begin]'，'[exp-end]'包裹小题的题目解析，在小题解析中，结尾处用[@难@]，[@中@]，[@易@]表示难易程度。 </li>
        <li> 9. 在答案正确的选项尾部加上'√'，作为标识。 </li>
        <li> 10. 出于架构设计，所有标签必填，内容可空。</li>
    </ul>";
        return $rules;
    }

    /**
     * 配置选项抬头
     * @return array
     */
    private function ConfigOpt(){
        $opt = [
            0=>'A',
            1=>'B',
            2=>'C',
            3=>'D',
            4=>'E',
            5=>'F',
            6=>'G',
            7=>'H',
            8=>'I',
            9=>'J',
        ];
        return $opt;
    }

}

//查看规则
//$rules = Paper::rules();
//echo $rules;

//new class查看规则
//$r = new Paper($content='',$showRules=1);

//查看样例运行结果
//$result = (new Paper())->get();
//echo "<pre>"; print_r($result);die;












