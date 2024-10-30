<?php
// Caminho: /public_html/modules/multi_pipeline/controllers/Multi_pipeline.php

defined('BASEPATH') or exit('No direct script access allowed');

class Multi_pipelines extends CI_Controller {

    public function pipelines() {
        // ...
    }

    public function edit($id) {
        // Load the edit pipeline view here
        $this->load->view('modules/multi_pipeline/views/pipelines/edit', array('id' => $id));
    }

}

/**
 * Multi Pipeline Controller
 */
class Multi_pipeline extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        // Carregue o modelo corretamente
        $this->load->model('multi_pipeline_model'); // O nome do modelo deve estar em minúsculas aqui para evitar problemas
        $this->load->library('form_validation');
        $this->load->model('Pipeline_model');
        $this->load->model('currencies_model');
        $this->load->model('Lead_model'); 
        $this->load->model('Api_model');
        $this->load->model('Multi_pipeline_model');
        $this->lang->load('multi_pipeline', 'portuguese_br');
        $this->lang->load('multi_pipeline', 'english');
    }

    /**
     * Index function - List all pipelines
     */
    public function index()
    {
        if (!has_permission('multi_pipeline', '', 'view')) {
            access_denied('multi_pipeline');
        }

        $data['title'] = _l('multi_pipeline');
        $staff_id = get_staff_user_id();
    
        // Verifica se o usuário é administrador
        if ($this->multi_pipeline_model->is_admin($staff_id)) {
            // Se for administrador, obtém todos os pipelines
            $data['pipelines'] = $this->multi_pipeline_model->get_pipelines();
        } else {
            // Se não for admin, obtém os pipelines atribuídos ao usuário ou à sua função
            $data['pipelines'] = $this->multi_pipeline_model->get_pipelines_for_user($staff_id);
        }

        // Exibe outros dados como summary, stages e leads
        $data['summary'] = [];
        $this->load->model('Leads_model');
        $data['statuses'] = $this->Leads_model->get_status();
        foreach ($data['statuses'] as $status) {
            $total_leads = total_rows(db_prefix() . 'leads', ['status' => $status['id']]);
            $this->db->select_sum('lead_value');
            $this->db->where('status', $status['id']);
            $value_result = $this->db->get(db_prefix() . 'leads')->row();
            $total_value = $value_result ? $value_result->lead_value : 0;
            $data['summary'][] = [
                'pipeline_id' => 0,
                'status_id' => $status['id'],
                'name' => $status['name'],
                'color' => $status['color'],
                'total' => $total_leads,
                'value' => $total_value
            ];
        }

        $data['stages'] = $this->multi_pipeline_model->get_stages();
        $data['leads'] = $this->multi_pipeline_model->get_leads_grouped();
        $data['bodyclass'] = 'kan-ban-body';
        $this->load->model('currencies_model');
        $base_currency = $this->currencies_model->get_base_currency();
        $data['base_currency'] = $base_currency ? $base_currency : (object)['symbol' => '$'];

        // Carrega a view de listagem dos pipelines
        $this->load->view('pipelines/list', $data);
    }
    
    /**
     * Create pipeline function
     */
    public function create_pipeline()
    {
        if (!has_permission('multi_pipeline', '', 'create')) {
            access_denied('create_pipeline');
        }
    
        if ($this->input->post()) {
            $this->form_validation->set_rules('name', _l('pipeline_name'), 'required|max_length[255]|is_unique[' . db_prefix() . 'multi_pipeline_pipelines.name]');
            $this->form_validation->set_rules('description', _l('pipeline_description'), 'trim');
    
            if ($this->form_validation->run() === TRUE) {
                $data = $this->input->post();
                $this->load->model('multi_pipeline_model');
                $pipeline_id = $this->multi_pipeline_model->add_pipeline($data); // Pipeline é criado e atribuições são feitas aqui
    
                if ($pipeline_id) {
                    log_activity('New Pipeline Created [ID: ' . $pipeline_id . ', Name: ' . $data['name'] . ']');
                    set_alert('success', _l('pipeline_created_successfully'));
                    redirect(admin_url('multi_pipeline/status/create/' . $pipeline_id));
                } else {
                    set_alert('danger', _l('pipeline_creation_failed'));
                }
            }
        }
    
        $data['title'] = _l('create_pipeline');
        $this->load->view('multi_pipeline/pipelines/create', $data);
    }

    public function summary()
    {
        if (!$this->Multi_pipeline_model) {
            $this->load->model('Multi_pipeline_model');
        }
        $data['pipelines'] = $this->Multi_pipeline_model->get_pipelines_with_stages_and_lead_count();
        $data['leads'] = $this->Multi_pipeline_model->get_all_leads();
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['title'] = _l('lead_summary');
        $this->load->view('multi_pipeline/leads/summary', $data);
    }

    public function change_lead_pipeline_stage()
{
    $lead_id = $this->input->post('lead_id');
    $pipeline_id = $this->input->post('pipeline_id');
    $stage_id = $this->input->post('stage_id');

    $result = $this->Multi_pipeline_model->update_lead_pipeline_stage($lead_id, $pipeline_id, $stage_id);

    if ($result) {
        echo json_encode(['success' => true, 'message' => _l('lead_pipeline_stage_updated_successfully')]);
    } else {
        echo json_encode(['success' => false, 'message' => _l('lead_pipeline_stage_update_failed')]);
    }
}

    /**
     * Salva uma associação de formulário
     */
    public function save_form_association()
    {
        if (!has_permission('multi_pipeline', '', 'create') && !has_permission('multi_pipeline', '', 'edit')) {
            access_denied('multi_pipeline');
        }

        $this->form_validation->set_rules('form_id', 'Formulário', 'required|integer');
        $this->form_validation->set_rules('pipeline_stage', 'Pipeline e Estágio', 'required');

        if ($this->form_validation->run() === FALSE) {
            set_alert('danger', validation_errors());
            redirect(admin_url('multi_pipeline'));
        }

        $pipeline_stage = explode(',', $this->input->post('pipeline_stage'));
        $pipeline_id = $pipeline_stage[0];
        $stage_id = $pipeline_stage[1];
        $form_id = $this->input->post('form_id');

        // Verifica se já existe associação para o formulário
        $existing = $this->db->where('form_id', $form_id)->get('multi_pipeline_form_associations')->row();
        if ($existing) {
            // Atualiza a associação existente
            $data = [
                'pipeline_id' => $pipeline_id,
                'stage_id' => $stage_id,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $this->Multi_pipeline_model->update_form_association($existing->id, $data);
            set_alert('success', 'Associação atualizada com sucesso.');
        } else {
            // Cria uma nova associação
            $data = [
                'form_id' => $form_id,
                'pipeline_id' => $pipeline_id,
                'stage_id' => $stage_id,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $new_id = $this->Multi_pipeline_model->add_form_association($data);
            if ($new_id) {
                set_alert('success', 'Associação criada com sucesso.');
            } else {
                set_alert('danger', 'Falha ao criar a associação.');
            }
        }

        redirect(admin_url('multi_pipeline'));
    }

    /**
     * Deleta uma associação de formulário
     * 
     * @param int $id
     */
    public function delete_form_association($id)
    {
        if (!has_permission('multi_pipeline', '', 'delete')) {
            access_denied('multi_pipeline');
        }

        if ($this->Multi_pipeline_model->delete_form_association($id)) {
            set_alert('success', 'Associação deletada com sucesso.');
        } else {
            set_alert('danger', 'Falha ao deletar a associação.');
        }

        redirect(admin_url('multi_pipeline'));
    }

    public function form_associations()
    {
        if (!has_permission('multi_pipeline', '', 'view')) {
            access_denied('multi_pipeline');
        }

        // Carregar dados necessários
        $data['forms'] = $this->db->get('tblweb_to_lead')->result_array();
        $data['pipelines'] = $this->Multi_pipeline_model->get_pipelines_with_stages();
        $data['associations'] = $this->Multi_pipeline_model->get_form_associations();
        $data['title'] = _l('form_associations');

        // Carregar a view
        $this->load->view('forms/form_associations', $data);
    }

    public function handle_web_form_lead($lead_data) {
    // Verifica se há um form_id
    if (!isset($lead_data['from_form_id'])) {
        return $lead_data;
    }

    // Busca a associação do formulário
    $form_association = $this->multi_pipeline_model->get_form_association_by_form_id($lead_data['from_form_id']);
    
    if ($form_association) {
        $lead_data['pipeline_id'] = $form_association->pipeline_id;
        $lead_data['stage_id'] = $form_association->stage_id;
    }
    
    return $lead_data;
}

    public function add_lead()
{
    if ($this->input->post()) {
        $data = $this->input->post();

        // Validar dados de entrada
        $this->load->library('form_validation');
        $this->form_validation->set_rules('name', 'Nome', 'required|trim');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|trim');
        $this->form_validation->set_rules('pipeline_id', 'Pipeline', 'required');
        $this->form_validation->set_rules('stage_id', 'Estágio', 'required');
        $this->form_validation->set_rules('status', 'Status', 'required');
        $this->form_validation->set_rules('source', 'Fonte', 'required');

        if ($this->form_validation->run() === FALSE) {
            echo json_encode(['success' => false, 'message' => validation_errors()]);
            return;
        }

        // Verificar se o lead já existe
        $this->load->model('Lead_model');
        $existing_lead = $this->Lead_model->get_lead_by_email($data['email']);
        if ($existing_lead) {
            echo json_encode(['success' => false, 'message' => 'Um lead com este email já existe.']);
            return;
        }

        // Tentar salvar o lead
        try {
            $lead_id = $this->Lead_model->add_lead($data);
            if ($lead_id) {
                echo json_encode(['success' => true, 'message' => 'Lead adicionado com sucesso!', 'lead_id' => $lead_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao adicionar lead.']);
            }
        } catch (Exception $e) {
            log_message('error', 'Erro ao adicionar lead: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro interno ao adicionar lead.']);
        }
        return;
    }

    // Carregar dados necessários para a view
    $data['pipelines'] = $this->Multi_pipeline_model->get_pipelines();
    $data['stages'] = $this->Multi_pipeline_model->get_stages();
    $data['statuses'] = $this->Lead_model->get_status();
    $data['sources'] = $this->Lead_model->get_sources();
    $data['staff'] = $this->Lead_model->get_staff();
    $data['title'] = _l('add_new_lead');

    $this->load->view('modules/multi_pipeline/views/leads/add_modal', $data);
}

public function manage_tokens()
{
    $this->load->model('Api_model');
    $data['tokens'] = $this->Api_model->get_all_tokens(); // Método que você deve implementar no Api_model
    $this->load->view('api/manage_tokens', $data);
}
}