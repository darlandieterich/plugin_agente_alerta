<?php
//confere se existe essa constante do moodle ou morre
defined('MOODLE_INTERNAL') || die();

class block_agente_alerta extends block_base
{
	//funcao que define o inicio do bloco
	function init() {
        //titulo do bloco, definido de acordo com a lingua
        $this->title = get_string('pluginname', 'block_agente_alerta');        
    }
    public function has_config() {
        return true;
    }
    
    //irá dizer em quais areas serao possiveis de ser agregadas o bloco
    function applicable_formats() {
        return array('all' => true, 'my' => true,'site-index' => true,'course-view-social' => true);
    }

    //função onde sera cofigurada a aplicação
    function get_content(){
    	global $USER,$DB,$CFG;
        
        //vetor de intruções de login
        $rlogin = array("Que tal fazer login?",
            "Olá vamos fazer o login?",
            "Que tal trabalhar um pouco? faça o login!",
            "Vamos estudar? faça o login!",
            "Confira suas tarefas! faça o login!");
		//inicializa a variavel texto
        $this->content->text = "";
        //se usuario não estiver logado
        if (!isset($USER->username))
        {
            //randomiza o vetor de intruções de login
            $this->content->text .= $rlogin[rand(0,count($rlogin)-1)];
        }
        else
        {
            //obtem os cursos em que usuario esta matriculado            
            $cursos = enrol_get_my_courses();
            //verifica se usuario esta matriculado
            if(count($cursos)==0)
                $this->content->text .= "<strong>".$USER->firstname."</strong> você não esta matriculado em nenhum curso!";
            else
            {
                //inicializa variavel como vazia
                $this->content->text .= "<h4>Olá ".$USER->firstname."!</h4><hr>Você esta matriculado em 
                ".count($cursos)." curso(s) <hr>";
                $contaref = 0;
                $cont_t_s = 0;
                $totaltarefas = 0;
                foreach ($cursos as $curso) {
                    
                    //sql para buscar tarefas que forem maiores de um periodo X
                    $sql_tarefas = "SELECT * FROM mdl_assignment ass WHERE ass.course = ? AND ass.timedue >= ".time();
                    //captura o id do usuário
                    $parametro = array($curso->id);
                    //obtem resultado da consulta em forma de objeto
                    $rstarefas=$DB->get_records_sql($sql_tarefas,$parametro);
                    //imprime as avaliaçãoes ativas
                    $this->content->text .= "<strong><a href='course/view.php?id=".$curso->id."'>".$curso->fullname.":</a></strong></br>";
                    //conta as tarefas
                    $totrs = count($rstarefas);
                    ////////////////////////////////////////
                    if($USER->lastlogin > $USER->lastcourseaccess[$curso->id] && $totrs > 0)
                    {
                        $this->content->text .= "<h5>Faz tempo que não passa por aqui!</h5>";
                        //$this->content->text .= date("d/m/Y H:i:s",$USER->lastlogin).'<br/>';
                        //$this->content->text .= date("d/m/Y H:i:s",$USER->lastcourseaccess[$curso->id]);
                    }
                    //print_r($rstarefas);
                    ////////////////////////////////////////
                    if($totrs>0)
                    {
                        foreach ($rstarefas as $chave => $dados)
                        {
                            $sql_tarefas_submetidas = "SELECT * FROM mdl_assignment_submissions asb WHERE asb.userid = ? AND asb.assignment = ?";
                            $parametrosub = array($USER->id,$dados->id);
                            //var_dump($dados);
                            $rstarefassub = $DB->get_records_sql($sql_tarefas_submetidas,$parametrosub);
                            $cont_tarefas_submetidas = count($rstarefassub);                            
                            if($cont_tarefas_submetidas > 0){                                
                                $this->content->text .= "
                                <div>".$dados->name." 
                                    [<img alt='Enviada' src='".$CFG->wwwroot.'/blocks/agente_alerta/pix/_submit.gif'."'>
                                    <a href='mod/assignment/view.php?id=".$this->get_id_assignment($dados->id)."'><img alt='Acessar tarefa' src='".$CFG->wwwroot.'/blocks/agente_alerta/pix/view_assign.gif'."'></a>]
                                    <span style='font-size:8px'>até ". date(" d/m/Y",$dados->timedue)."
                                    </span>
                                </div>";
                                $cont_t_s++;
                            }
                            else                                                                                               
                                $this->content->text .= "
                            <div>".$dados->name." 
                                [<img alt='Não enviada' src='".$CFG->wwwroot.'/blocks/agente_alerta/pix/_nsubmit.gif'."'>
                                <a href='mod/assignment/view.php?id=".$this->get_id_assignment($dados->id)."'><img alt='Acessar tarefa' src='".$CFG->wwwroot.'/blocks/agente_alerta/pix/view_assign.gif'."'></a>]
                                <span style='font-size:8px'> até ". date(" d/m/Y",$dados->timedue)."
                                </span>
                            </div>";
                        }
                    }else
                        $this->content->text .= "Sem Tarefas! <img alt='Sem tarefas!' src='".$CFG->wwwroot.'/blocks/agente_alerta/pix/smile.gif'."'></br>";
                    //acomula as tarefas//
                    $contaref += $totrs;
                }
                $this->content->text .= "<hr>";
                $this->content->text .= "Tarefas em aberto:<strong> ".$contaref."</strong></br>";
                $this->content->text .= "Tarefas submetidas:<strong> ".$cont_t_s."</strong></br>";
                /*
                Calculo de desempenho, usando "Regra de Três" onde  
                suas tarefas existentes por suas tarefas submetidas
                FORMULA: ______100_______ * tarefasSubmetidas = VALOR
                        tarefasExistentes
                */
                $this->content->text .= "Desempenho tarefas:<strong>".round((100/$this->count_assignmnet_user())*$this->count_assignment_submited())."%</strong>";
            }
            //var_dump($USER);
            //echo(MOODLE_INTERNAL);
            //$oi= assignment_count_real_submissions(CONTEXT_MODULE,null);
            // retorna o conteudo do objeto content
            return $this->content;
        }        
    }
    function get_id_assignment($id)
    {
        global $DB;
        $sql   = "SELECT * FROM mdl_course_modules cm WHERE cm.module = 1 AND cm.instance = ?";
        $param = array($id);
        $data  = $DB->get_record_sql($sql,$param);
        return $data->id;
    }
    function count_assignmnet_user()
    {
        global $DB;
        $cursos = enrol_get_my_courses();
        $cont_tarefas = 0;
        foreach ($cursos as $curso)
        {            
            $sql_tarefas = "SELECT * FROM mdl_assignment asg WHERE asg.course = ?";
            $parametro = array($curso->id);
            $rstarefas=$DB->get_records_sql($sql_tarefas,$parametro);
            foreach ($rstarefas as $tarefa) {
                $cont_tarefas++;
            }
        }
        return $cont_tarefas;
    }
    function count_assignment_submited()
    {
        global $DB,$USER;        
        $cont_tarefas_sub = 0;                           
        $sql_tarefas = "SELECT * FROM  mdl_assignment_submissions asb WHERE asb.userid = ?";
        $parametro = array($USER->id);
        $rstarefas=$DB->get_records_sql($sql_tarefas,$parametro);
        foreach ($rstarefas as $tarefa) {
            $cont_tarefas_sub++;
        }        
        return $cont_tarefas_sub;
    }
}