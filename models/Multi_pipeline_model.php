<?php
// Caminho: /public_html/modules/multi_pipeline/models/Multi_pipeline_model.php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Multi Pipeline Model
 */
class Multi_pipeline_model extends App_Model
{
    // Declaração explícita das propriedades
    protected $table_pipelines;
    protected $table_stages;
    protected $table_leads;

    public function __construct()
    {
        parent::__construct();
        
        $this->load->database();
        $this->load->helper('url');
        $this->load->helper('date');
        $this->load->library('encryption');
        
        // Carregar modelos relacionados
        $this->load->model('multi_pipeline/Pipeline_model', 'pipeline_model');
        $this->load->model('multi_pipeline/Lead_model', 'lead_model'); // Corrigido para carregar o modelo Lead_model
        
        // Inicializar variáveis de configuração
        $this->table_pipelines = db_prefix() . 'multi_pipeline_pipelines';
        $this->table_stages = db_prefix() . 'multi_pipeline_stages';
        $this->table_leads = db_prefix() . 'multi_pipeline_leads';
    }

    /**
 * Obtém todos os pipelines ou um pipeline específico pelo ID.
 *
 * @param int|null $id O ID do pipeline (opcional).
 * @param array $where Um array de condições WHERE adicionais (opcional).
 * @return array|object Um array de pipelines ou um objeto de pipeline se $id for fornecido.
 */
public function get_pipelines($where = [], $limit = '', $start = '')
{
    // Seleciona todos os campos da tabela tblmulti_pipeline_pipelines
    $this->db->select('*');
    $this->db->from('tblmulti_pipeline_pipelines');  // Corrigido: Não use o `get` imediatamente aqui
    
    // Aplicar filtros adicionais se fornecidos
    if (!empty($where)) {
        $this->db->where($where);
    }

    // Aplicar limitação se fornecida
    if ($limit !== '') {
        $this->db->limit($limit, $start);
    }

    // Executa a consulta e retorna os resultados
    return $this->db->get()->result_array();  // Correção: Chamar o `get()` apenas uma vez no final
}

/**
 * Obtém todos os estágios de um pipeline pelo ID do pipeline.
 *
 * @param int $pipeline_id O ID do pipeline.
 * @return array Um array de estágios do pipeline.
 */
public function get_pipeline_stages($pipeline_id)
{
    // Verificar se o parâmetro $pipeline_id é válido e seguro
    if (!is_numeric($pipeline_id) || $pipeline_id < 1) {
        throw new InvalidArgumentException('Invalid pipeline ID');
        
        
    }

    $this->db->select('mps.*')
             ->from('tblmulti_pipeline_stages mps')
             ->join('tblmulti_pipeline_pipelines mpp', 'mpp.id = mps.pipeline_id')
             ->where('mpp.id', $pipeline_id);

    return $this->db->get()->result_array();
}

    /**
     * Update a pipeline
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_pipeline($id, $data)
    {
        $this->db->where('id', $id);
        $this->db->update('tblmulti_pipeline_pipelines', $data);
        return $this->db->affected_rows() > 0;
    }

    /**
 * Obtém um pipeline específico pelo ID
 *
 * @param int $id ID do pipeline
 * @return array Dados do pipeline
 */
public function get_pipeline($id)
{
    $this->db->where('id', $id);
    return $this->db->get('tblmulti_pipeline_pipelines')->row_array();
}

    public function update_pipeline_assignments($pipeline_id, $ids, $type)
    {
        // Remove as atribuições antigas
        if ($type == 'staff') {
            $this->db->where('pipeline_id', $pipeline_id);
            $this->db->where('staff_id IS NOT NULL');
            $this->db->delete('tblmulti_pipeline_assignments');
    
            // Adiciona as novas atribuições de staff
            foreach ($ids as $staff_id) {
                $this->db->insert('tblmulti_pipeline_assignments', [
                    'pipeline_id' => $pipeline_id,
                    'staff_id' => $staff_id
                ]);
            }
        } elseif ($type == 'role') {
            $this->db->where('pipeline_id', $pipeline_id);
            $this->db->where('role_id IS NOT NULL');
            $this->db->delete('tblmulti_pipeline_assignments');
    
            // Adiciona as novas atribuições de roles
            foreach ($ids as $role_id) {
                $this->db->insert('tblmulti_pipeline_assignments', [
                    'pipeline_id' => $pipeline_id,
                    'role_id' => $role_id
                ]);
            }
        }
    }    

    public function get_pipeline_assignments($pipeline_id)
{
    // Recupera atribuições de membros e funções
    $this->db->select('staff_id, role_id');
    $this->db->where('pipeline_id', $pipeline_id);
    $assignments = $this->db->get('tblmulti_pipeline_assignments')->result_array();

    $staff = array_filter($assignments, fn($assignment) => $assignment['staff_id']);
    $roles = array_filter($assignments, fn($assignment) => $assignment['role_id']);

    return ['staff' => $staff, 'roles' => $roles];
    return $this->db->get('tblmulti_pipeline_assignments')->result_array();
}

    /**
     * Exclui um pipeline e lida com dados relacionados
     * @param int $id ID do pipeline a ser excluído
     * @return bool Verdadeiro se excluído com sucesso, falso caso contrário
     */

public function delete_pipeline($id)
{
    $this->db->trans_start();

    // Excluir pipeline principal
    $this->db->where('id', $id)->delete('tblmulti_pipeline_pipelines');
    
    // Excluir atribuições associadas na tabela tblmulti_pipeline_assignments
    $this->db->where('pipeline_id', $id)->delete('tblmulti_pipeline_assignments');

    // Excluir estágios associados
    $this->db->where('pipeline_id', $id)->delete('tblmulti_pipeline_stages');
    
    // Atualizar leads associados, removendo referências ao pipeline e estágio
    $this->db->where('pipeline_id', $id)
             ->update('tblleads', ['pipeline_id' => null, 'stage_id' => null]);
    
    $this->db->trans_complete();

    return $this->db->trans_status();
}

/**
 * Adiciona um novo pipeline ao banco de dados
 *
 * @param array $data Dados do pipeline a ser adicionado
 * @return int ID do pipeline recém-inserido
 */
public function add_pipeline($data) {
    // Insira os dados básicos do pipeline
    $pipeline_data = [
        'name' => $data['name'],
        'description' => $data['description']
    ];

    // Insere o pipeline na tabela 'tblmulti_pipeline_pipelines'
    $this->db->insert('tblmulti_pipeline_pipelines', $pipeline_data);
    $pipeline_id = $this->db->insert_id(); // Obtém o ID do pipeline criado

    // Agora vamos associar os membros da equipe e funções à tabela 'tblmulti_pipeline_assignments'
    if (isset($data['staff_ids']) && is_array($data['staff_ids'])) {
        foreach ($data['staff_ids'] as $staff_id) {
            $this->db->insert('tblmulti_pipeline_assignments', [
                'pipeline_id' => $pipeline_id,
                'staff_id' => $staff_id
            ]);
        }
    }

    if (isset($data['role_ids']) && is_array($data['role_ids'])) {
        foreach ($data['role_ids'] as $role_id) {
            $this->db->insert('tblmulti_pipeline_assignments', [
                'pipeline_id' => $pipeline_id,
                'role_id' => $role_id
            ]);
        }
    }

    return $pipeline_id;
}

/**
 * Adiciona um novo status (estágio) a um pipeline
 *
 * @param array $data Dados do status a ser adicionado
 * @return int ID do status recém-inserido
 */
public function add_status($data) {
    $pipeline_id = $data['pipeline_id'];
    // Obtém o nome do pipeline correspondente ao ID fornecido
    $pipeline_name = $this->db->select('name')->from('tblmulti_pipeline_pipelines')->where('id', $pipeline_id)->get()->row()->name;

    $data['pipeline_name'] = $pipeline_name; // Adiciona o nome do pipeline aos dados

    $this->db->insert('tblmulti_pipeline_stages', $data);
    return $this->db->insert_id();
}

/**
 * Obtém os estágios de um pipeline específico ou todos os estágios
 *
 * @param int|null $pipeline_id ID do pipeline (opcional)
 * @return array Lista de estágios
 */
public function get_stages($pipeline_id = null)
{
    $this->db->select('*');
    $this->db->from('tblmulti_pipeline_stages');
    
    if ($pipeline_id !== null) {
        $this->db->where('pipeline_id', $pipeline_id);
    }
    
    $this->db->order_by('order', 'ASC');
    return $this->db->get()->result_array();
}

/**
 * Obtém leads agrupados por pipeline e estágio
 *
 * @return array Leads agrupados por pipeline e estágio
 */
public function get_leads_grouped()
{
    $this->db->select('tblleads.*, tblmulti_pipeline_stages.name as stage_name, tblmulti_pipeline_stages.color as stage_color, tblmulti_pipeline_pipelines.id as pipeline_id, tblmulti_pipeline_pipelines.name as pipeline_name');
    $this->db->from('tblleads');
    $this->db->join('tblmulti_pipeline_stages', 'tblmulti_pipeline_stages.id = tblleads.stage_id', 'left');
    $this->db->join('tblmulti_pipeline_pipelines', 'tblmulti_pipeline_pipelines.id = tblleads.pipeline_id', 'left');
    $leads = $this->db->get()->result_array();

    $grouped_leads = [];
    foreach ($leads as $lead) {
        $pipeline_id = $lead['pipeline_id'];
        $stage_id = $lead['stage_id'];
        if (!isset($grouped_leads[$pipeline_id])) {
            $grouped_leads[$pipeline_id] = [];
        }
        if (!isset($grouped_leads[$pipeline_id][$stage_id])) {
            $grouped_leads[$pipeline_id][$stage_id] = [
                'stage_id' => $stage_id,
                'stage_name' => $lead['stage_name'],
                'stage_color' => $lead['stage_color'],
                'leads' => []
            ];
        }
        $grouped_leads[$pipeline_id][$stage_id]['leads'][] = $lead;
    }

    return $grouped_leads;
}

/**
 * Atualiza um status (estágio) do pipeline
 *
 * @param int $id ID do status
 * @param array $data Dados a serem atualizados
 * @return bool Resultado da atualização
 */
public function update_status($id, $data)
{
    $this->db->where('id', $id);
    return $this->db->update('tblmulti_pipeline_stages', $data);
}

/**
 * Obtém um status (estágio) específico pelo ID
 *
 * @param int $id ID do status
 * @return array Dados do status
 */
public function get_status($id)
{
    $this->db->where('id', $id);
    return $this->db->get('tblmulti_pipeline_stages')->row_array();
}

/**
 * Exclui um status (estágio) do pipeline
 *
 * @param int $id ID do status a ser excluído
 * @return bool Resultado da exclusão
 */
public function delete_status($id)
{
    $this->db->where('id', $id);
    return $this->db->delete('tblmulti_pipeline_stages');
}

/**
 * Obtém todos os status (estágios) com contagem de leads e nome do pipeline
 *
 * @return array Lista de status com contagem de leads e nome do pipeline
 */
public function get_all_statuses_with_lead_count()
{
    $this->db->select('tblmulti_pipeline_stages.*, tblmulti_pipeline_pipelines.name as pipeline_name, COUNT(tblleads.id) as lead_count');
    $this->db->from('tblmulti_pipeline_stages');
    $this->db->join('tblmulti_pipeline_pipelines', 'tblmulti_pipeline_pipelines.id = tblmulti_pipeline_stages.pipeline_id', 'left');
    $this->db->join('tblleads', 'tblleads.stage_id = tblmulti_pipeline_stages.id', 'left');
    $this->db->group_by('tblmulti_pipeline_stages.id');
    $this->db->order_by('tblmulti_pipeline_stages.pipeline_id, tblmulti_pipeline_stages.order');
    return $this->db->get()->result_array();
}

/**
 * Obtém todos os leads com informações de pipeline e estágio
 *
 * @return array Lista de todos os leads com detalhes de pipeline e estágio
 */
public function get_all_leads()
{
    $this->db->select('tblleads.*, tblmulti_pipeline_pipelines.name as pipeline_name, tblmulti_pipeline_stages.name as stage_name');
    $this->db->from('tblleads');
    $this->db->join('tblmulti_pipeline_pipelines', 'tblleads.pipeline_id = tblmulti_pipeline_pipelines.id', 'left');
    $this->db->join('tblmulti_pipeline_stages', 'tblleads.stage_id = tblmulti_pipeline_stages.id', 'left');
    return $this->db->get()->result_array();
}

/**
 * Obtém todos os pipelines com seus estágios e contagem de leads
 *
 * @return array Lista de pipelines com estágios e contagem de leads
 */
public function get_pipelines_with_stages_and_lead_count()
{
    $pipelines = $this->get_pipelines();
    foreach ($pipelines as &$pipeline) {
        $pipeline['stages'] = $this->get_pipeline_stages($pipeline['id']);
        $pipeline['lead_count'] = $this->db->where('pipeline_id', $pipeline['id'])->count_all_results('tblleads');
    }
    return $pipelines;
}

/**
 * Atualiza o pipeline e o estágio de um lead
 *
 * @param int $lead_id ID do lead
 * @param int $pipeline_id ID do novo pipeline
 * @param int $stage_id ID do novo estágio
 * @return bool Verdadeiro se atualizado com sucesso, falso caso contrário
 */
public function update_lead_pipeline_stage($lead_id, $pipeline_id, $stage_id)
{
    $this->db->where('id', $lead_id);
    $result = $this->db->update('tblleads', [
        'pipeline_id' => $pipeline_id,
        'stage_id' => $stage_id
    ]);

    return $this->db->affected_rows() > 0;
}

    /**
     * Adiciona uma nova associação de formulário
     * 
     * @param array $data Dados da associação a ser inserida
     * @return int|bool ID da associação inserida ou false em falha
     */
    public function add_form_association($data)
    {
        $this->db->insert('multi_pipeline_form_associations', $data);
        return ($this->db->affected_rows() > 0) ? $this->db->insert_id() : false;
    }

    /**
     * Atualiza uma associação de formulário existente
     * 
     * @param int $id ID da associação a ser atualizada
     * @param array $data Novos dados da associação
     * @return bool Verdadeiro se atualizado com sucesso, falso caso contrário
     */
    public function update_form_association($id, $data)
    {
        $this->db->where('id', $id);
        $this->db->update('multi_pipeline_form_associations', $data);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Exclui uma associação de formulário
     * 
     * @param int $id ID da associação a ser excluída
     * @return bool Verdadeiro se excluído com sucesso, falso caso contrário
     */
    public function delete_form_association($id)
    {
        $this->db->where('id', $id);
        $this->db->delete('multi_pipeline_form_associations');
        return $this->db->affected_rows() > 0;
    }

    /**
     * Obtém as associações de formulários com informações relacionadas
     *
     * @return array Lista de associações de formulários com detalhes de formulário, pipeline e estágio
     */
    public function get_form_associations()
    {
        $this->db->select('fa.id, f.name as form_name, p.name as pipeline_name, s.name as stage_name');
        $this->db->from('multi_pipeline_form_associations fa');
        $this->db->join('tblweb_to_lead f', 'fa.form_id = f.id', 'left');
        $this->db->join('multi_pipeline_pipelines p', 'fa.pipeline_id = p.id', 'left');
        $this->db->join('multi_pipeline_stages s', 'fa.stage_id = s.id', 'left');
        return $this->db->get()->result_array();
    }

    public function get_form_association_by_form_id($form_id) {
        return $this->db->get_where('tblmulti_pipeline_form_associations', [
            'form_id' => $form_id
        ])->row();
    }

    /**
     * Obtém os pipelines com seus estágios
     *
     * @return array Lista de pipelines com seus respectivos estágios
     */
    public function get_pipelines_with_stages()
    {
        $this->db->select('p.id as pipeline_id, p.name as pipeline_name, s.id as stage_id, s.name as stage_name');
        $this->db->from('multi_pipeline_pipelines p');
        $this->db->join('multi_pipeline_stages s', 'p.id = s.pipeline_id', 'left');
        $this->db->order_by('p.id, s.order', 'ASC');
        $pipelines = $this->db->get()->result_array();

        $result = [];
        foreach ($pipelines as $pipeline) {
            $result[$pipeline['pipeline_id']]['pipeline_name'] = $pipeline['pipeline_name'];
            $result[$pipeline['pipeline_id']]['stages'][] = [
                'id' => $pipeline['stage_id'],
                'name' => $pipeline['stage_name']
            ];
        }
        return $result;
    }
    
    public function delete_assignment($id) {
        return $this->db->delete('tblmulti_pipeline_assignments', ['id' => $id]);
    }

    // Adicionar métodos para gerenciar as atribuições de pipelines

    public function get_staff_and_roles() {
        $staff = $this->db->get('tblstaff')->result_array();
        $roles = $this->db->get('tblroles')->result_array();

        return [
            'staff' => $staff,
            'roles' => $roles
        ];
    }

    public function get_pipelines_for_user($staff_id)
    {
        // Obtém o ID da função (role_id) do usuário logado
        $this->db->select('role');
        $this->db->from('tblstaff');
        $this->db->where('staffid', $staff_id);
        $user_role = $this->db->get()->row();
        $role_id = $user_role ? $user_role->role : null;
    
        // Consulta para obter pipelines baseados em staff_id ou role_id
        $this->db->select('p.*');
        $this->db->from('tblmulti_pipeline_pipelines p');
        $this->db->join('tblmulti_pipeline_assignments a', 'p.id = a.pipeline_id', 'inner');
        $this->db->group_start();
        $this->db->where('a.staff_id', $staff_id); // Verifica atribuição por staff_id
        if ($role_id) {
            $this->db->or_where('a.role_id', $role_id); // Verifica atribuição por role_id
        }
        $this->db->group_end();
        $this->db->group_by('p.id');
        
        return $this->db->get()->result_array();
    }

public function is_admin($staff_id)
{
    $this->db->where('staffid', $staff_id);
    $staff = $this->db->get('tblstaff')->row();
    return ($staff && $staff->admin == 1);
}

public function add_pipeline_assignment($pipeline_id, $staff_id = null, $role_id = null)
{
    $data = [
        'pipeline_id' => $pipeline_id,
        'staff_id' => $staff_id,
        'role_id' => $role_id
    ];
    return $this->db->insert('tblmulti_pipeline_assignments', $data);
}

public function remove_pipeline_assignment($pipeline_id, $staff_id = null, $role_id = null)
{
    $this->db->where('pipeline_id', $pipeline_id);
    if ($staff_id) {
        $this->db->where('staff_id', $staff_id);
    }
    if ($role_id) {
        $this->db->where('role_id', $role_id);
    }
    return $this->db->delete('tblmulti_pipeline_assignments');
}
}
