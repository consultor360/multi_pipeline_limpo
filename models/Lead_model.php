<?php
// Caminho: /public_html/modules/multi_pipeline/models/Lead_model.php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Modelo para gerenciamento de leads no sistema de múltiplos pipelines
 */
class Lead_model extends App_Model
{
        // Declaração explícita das propriedades de tabelas
        protected $table_leads;
        protected $table_pipeline_stages;
        protected $table_pipeline_pipelines;
        protected $table_form_associations;
        protected $table_web_to_lead;
        protected $table_leads_sources;
        protected $table_leads_status;
        protected $table_staff;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->load->database();
        $this->load->helper('url');
        $this->load->helper('date');
        
        // Carregar o banco de dados e outras bibliotecas necessárias
        $this->load->database();
        
        // Inicializar as variáveis de tabela com o prefixo do banco de dados
        $this->table_leads = db_prefix() . 'leads';
        $this->table_pipeline_stages = db_prefix() . 'multi_pipeline_stages';
        $this->table_pipeline_pipelines = db_prefix() . 'multi_pipeline_pipelines';
        $this->table_form_associations = db_prefix() . 'multi_pipeline_form_associations';
        $this->table_web_to_lead = db_prefix() . 'web_to_lead';
        $this->table_leads_sources = db_prefix() . 'leads_sources';
        $this->table_leads_status = db_prefix() . 'leads_status';
        $this->table_staff = db_prefix() . 'staff';
    }

    /**
     * Obtém leads agrupados por estágio para um pipeline específico
     *
     * @param int $pipeline_id ID do pipeline
     * @return array Leads agrupados por estágio
     */
    public function get_leads_by_pipeline_and_stage($pipeline_id)
    {
        if (!$pipeline_id) {
            return [];
        }
        
        // Seleciona os dados necessários dos leads e estágios
        $this->db->select('tblleads.*, tblmulti_pipeline_stages.name as stage_name, tblmulti_pipeline_stages.order as stage_order');
        $this->db->from('tblleads');
        $this->db->join('tblmulti_pipeline_stages', 'tblleads.stage_id = tblmulti_pipeline_stages.id', 'left');
        
        // Aplica filtros para garantir dados válidos
        $this->db->where('tblleads.pipeline_id', $pipeline_id);
        $this->db->where('tblleads.pipeline_id IS NOT NULL');
        $this->db->where('tblleads.pipeline_id !=', '');
        $this->db->where('tblleads.stage_id IS NOT NULL');
        
        // Ordena os resultados
        $this->db->order_by('tblmulti_pipeline_stages.order', 'ASC');
        $this->db->order_by('tblleads.dateadded', 'DESC');

        $query = $this->db->get();
        $results = $query->result_array();

        // Agrupa os leads por estágio
        $grouped_leads = [];
        foreach ($results as $lead) {
            $stage_id = $lead['stage_id'];
            if (!isset($grouped_leads[$stage_id])) {
                $grouped_leads[$stage_id] = [
                    'stage_name' => $lead['stage_name'],
                    'stage_order' => $lead['stage_order'],
                    'leads' => []
                ];
            }
            $grouped_leads[$stage_id]['leads'][] = $lead;
        }

        return $grouped_leads;
    }

    /**
     * Adiciona um novo lead na tabela tblleads
     *
     * @param array $data Dados do lead
     * @return int|bool ID do lead inserido ou false em falha
     */
    public function add_lead($data)
    {
        // Verificar se o lead já existe
        $existing_lead = $this->db->get_where('tblleads', ['email' => $data['email']])->row();
        if ($existing_lead) {
            return ['error' => true, 'message' => 'Um lead com este email já existe.'];
        }

        // Preparar os dados para inserção
        $insert_data = [
            'pipeline_id'   => $data['pipeline_id'],
            'stage_id'      => $data['stage_id'],
            'status'        => $data['status'],
            'source'        => $data['source'],
            'assigned'      => !empty($data['assigned']) ? $data['assigned'] : null,
            'name'          => $data['name'],
            'title'         => isset($data['title']) ? $data['title'] : null,
            'email'         => $data['email'],
            'website'       => isset($data['website']) ? $data['website'] : null,
            'phonenumber'   => isset($data['phonenumber']) ? $data['phonenumber'] : null,
            'lead_value'    => isset($data['lead_value']) ? $data['lead_value'] : null,
            'company'       => isset($data['company']) ? $data['company'] : null,
            'address'       => isset($data['address']) ? $data['address'] : null,
            'city'          => isset($data['city']) ? $data['city'] : null,
            'state'         => isset($data['state']) ? $data['state'] : null,
            'country'       => isset($data['country']) ? $data['country'] : null,
            'zip'           => isset($data['zip']) ? $data['zip'] : null,
            'description'   => isset($data['description']) ? $data['description'] : null,
            'dateadded'     => date('Y-m-d H:i:s'),
        ];

        // Inserir os dados na tabela tblleads
        try {
            $this->db->insert('tblleads', $insert_data);
            if ($this->db->affected_rows() > 0) {
                return $this->db->insert_id();
            } else {
                return ['error' => true, 'message' => 'Falha ao inserir o lead.'];
            }
        } catch (Exception $e) {
            log_message('error', 'Erro ao adicionar lead: ' . $e->getMessage());
            return ['error' => true, 'message' => 'Erro interno ao adicionar lead.'];
        }
    }

    /**
     * Obtém todas as fontes de leads
     *
     * @return array Lista de fontes de leads
     */
    public function get_sources()
    {
        return $this->db->get('tblleads_sources')->result_array();
    }

    /**
     * Obtém todos os status de leads
     *
     * @return array Lista de status de leads
     */
    public function get_status()
    {
        return $this->db->get('tblleads_status')->result_array();
    }

    /**
     * Obtém todos os membros da equipe
     *
     * @return array Lista de membros da equipe
     */
    public function get_staff()
    {
        return $this->db->get_staff('tblstaff')->result_array();
    }

    /**
     * Obtém um lead por email
     *
     * @param string $email Email do lead
     * @return object|bool Lead encontrado ou false em caso de erro
     */
    public function get_lead_by_email($email)
    {
        $this->db->where('email', $email);
        $query = $this->db->get('tblleads');
        return $query->row();
    }

    public function get_statuses_perfex() {
        // Seleciona as colunas específicas da tabela 'tblleads_status' tabela de status padrão do Perfex CRM
        $this->db->select('id, name, statusorder, color, isdefault');
        
        // Define a tabela a ser consultada
        $this->db->from('tblleads_status');
        
        // Executa a consulta no banco de dados
        $query = $this->db->get();
        
        // Verifica se a consulta retornou algum resultado
        if ($query->num_rows() > 0) {
            // Retorna os resultados como um array associativo
            return $query->result_array();
        } else {
            // Retorna um array vazio se nenhum registro for encontrado
            return [];
        }
    }

    public function add_lead_api($data)
{
    // Validação dos campos obrigatórios
    $required_fields = ['name', 'email', 'pipeline_id', 'stage_id', 'status', 'source'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return [
                'success' => false,
                'message' => "O campo '{$field}' é obrigatório.",
                'code' => 400
            ];
        }
    }

    // Verificar se o lead já existe
    $existing_lead = $this->db->get_where('tblleads', ['email' => $data['email']])->row();
    if ($existing_lead) {
        return [
            'success' => false,
            'message' => 'Um lead com este email já existe.',
            'code' => 409
        ];
    }

    // Preparar os dados para inserção
    $insert_data = [
        'name' => $data['name'],
        'email' => $data['email'],
        'pipeline_id' => $data['pipeline_id'],
        'stage_id' => $data['stage_id'],
        'status' => $data['status'],
        'source' => $data['source'],
        'assigned' => isset($data['assigned']) ? $data['assigned'] : null,
        'title' => isset($data['title']) ? $data['title'] : null,
        'company' => isset($data['company']) ? $data['company'] : null,
        'description' => isset($data['description']) ? $data['description'] : null,
        'country' => isset($data['country']) ? $data['country'] : null,
        'zip' => isset($data['zip']) ? $data['zip'] : null,
        'city' => isset($data['city']) ? $data['city'] : null,
        'state' => isset($data['state']) ? $data['state'] : null,
        'address' => isset($data['address']) ? $data['address'] : null,
        'website' => isset($data['website']) ? $data['website'] : null,
        'phonenumber' => isset($data['phonenumber']) ? $data['phonenumber'] : null,
        'is_public' => isset($data['is_public']) ? $data['is_public'] : 1,
        'lead_value' => isset($data['lead_value']) ? $data['lead_value'] : null,
        'dateadded' => date('Y-m-d H:i:s')
    ];

    // Inserir os dados na tabela tblleads
    $this->db->trans_start();
    $this->db->insert('tblleads', $insert_data);
    $insert_id = $this->db->insert_id();
    $this->db->trans_complete();

    if ($this->db->trans_status() === FALSE) {
        // Erro na transação
        log_message('error', 'Falha ao inserir lead via API: ' . $this->db->error()['message']);
        return [
            'success' => false,
            'message' => 'Erro interno ao adicionar lead.',
            'code' => 500
        ];
    }

    // Lead adicionado com sucesso
    return [
        'success' => true,
        'message' => 'Lead adicionado com sucesso.',
        'lead_id' => $insert_id,
        'code' => 201
    ];
}
}
