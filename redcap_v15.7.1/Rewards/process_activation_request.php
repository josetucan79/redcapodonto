<?php

use Vanderbilt\REDCap\Classes\Rewards\Utility\ActivationApprovalManager;


require_once dirname(__FILE__, 2) . '/Config/init_project.php';

if (!(SUPER_USER || (!$rewards_enabled_by_super_users_only && $user_rights['design']))) {
  redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
  // exit("ERROR: You must be a super user to perform this action!");
}

function processRequest($project_id, $request_id, $userid, bool $accept, bool $enable) {
  $manager = new ActivationApprovalManager($project_id);
  return $manager->processRequest($request_id, $userid, $accept, $enable);
}

$project_id = $_GET['pid'] ?? null;
$enable = ($_GET['enable'] ?? null) == 1;
$request_id = $_GET['request_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	header('Content-Type: application/json');
	
	try {
		$input = file_get_contents('php://input');
    $payload = json_decode($input, true);

    if (!$payload) {
        throw new Exception('Invalid JSON payload');
    }

    $csrf_token  = $payload['redcap_csrf_token'] ?? null;
    System::checkCsrfToken($csrf_token.'asdasd');

    $action      = $payload['action']      ?? null;
    $post_request_id  = $payload['request_id']  ?? null;
    $post_project_id  = $payload['project_id']  ?? null;
		
		if (!$action || !$post_request_id || !$post_project_id) {
			throw new Exception('Missing required parameters');
		}
		
		if (!in_array($action, ['accept', 'reject'])) {
			throw new Exception('Invalid action');
		}
		
		if (!is_numeric($post_request_id) || !is_numeric($post_project_id)) {
			throw new Exception('Invalid parameter format');
		}
		
		if ($post_project_id != $project_id) {
			throw new Exception('Project ID mismatch');
		}
		
		$accept = ($action === 'accept');
		$result = processRequest($post_project_id, $post_request_id, USERID, $accept, $enable);
		
		echo json_encode([
			'success' => true,
			'action' => $action,
			'message' => $action === 'accept' ? 'Request accepted successfully' : 'Request rejected successfully'
		]);
		exit;
		
	} catch (\Throwable $th) {
		echo json_encode([
			'success' => false,
			'error' => $th->getMessage()
		]);
		exit;
	}
}
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
//  include __DIR__.'/partials/header.php'; ?>

<div style="width: auto; max-width: 800px;">
    <h1><?= $enable ? 'Enable' : 'Disable' ?> Rewards (Participant Compensation)</h1>
    <div id="message-container" style="margin-bottom: 15px; display: none;">
        <div id="message" style="padding: 10px; border-radius: 4px;"></div>
    </div>
    <form id="approval-form">
        <input type="hidden" name="redcap_csrf_token" value="<?php echo System::getCsrfToken(); ?>">
        <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request_id); ?>">
        
        <div style="margin-bottom: 20px;">
            <p>This request will <strong><?= $enable ? 'enable' : 'disable' ?></strong> the <strong>Rewards</strong> module for your project. The Rewards feature provides a way to track and issue participant compensation (such as gift cards or electronic payments).</p>
            <p>Please review carefully before enabling, as this feature may involve financial transactions and compliance requirements.</p>
            <p>Once enabled, additional configuration will be needed to define compensation methods and manage participant rewards.</p>
            <?php if (!$project_id || !$request_id): ?>
                <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px;">
                    Error: Missing required parameters (project_id or request_id)
                </div>
            <?php endif; ?>
        </div>
        
        <div style="margin-bottom: 20px;">
            <button type="button" id="accept-button" class="btn btn-success" style="margin-right: 10px;" <?php echo (!$project_id || !$request_id) ? 'disabled' : ''; ?>>
                Accept Request
            </button>
            <button type="button" id="reject-button" class="btn btn-danger" <?php echo (!$project_id || !$request_id) ? 'disabled' : ''; ?>>
                Reject Request
            </button>
        </div>
        
        <div id="loading" style="display: none; margin-top: 10px;">
            <i class="fas fa-spinner fa-spin"></i> Processing...
        </div>
    </form>
</div>
<script type="module">

  const acceptButton = document.querySelector('#accept-button');
  const rejectButton = document.querySelector('#reject-button');
  const loading = document.querySelector('#loading');
  const messageContainer = document.querySelector('#message-container');
  const messageDiv = document.querySelector('#message');
  const form = document.querySelector('#approval-form');

  function showMessage(message, type = 'success') {
    messageDiv.textContent = message;
    messageDiv.className = type === 'success' ? 'alert alert-success' : 'alert alert-danger';
    messageDiv.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
    messageDiv.style.color = type === 'success' ? '#155724' : '#721c24';
    messageDiv.style.border = type === 'success' ? '1px solid #c3e6cb' : '1px solid #f5c6cb';
    messageContainer.style.display = 'block';
  }

  function setLoading(isLoading) {
    loading.style.display = isLoading ? 'block' : 'none';
    acceptButton.disabled = isLoading;
    rejectButton.disabled = isLoading;
  }

  function closeIframe() {
    if (window.self !== window.top) {
      window.top.$('.iframe-container').fadeOut(200, function(){
        window.top.location.reload();
      });
    }
  }

  async function processRequest(action) {
    const projectId = form.querySelector('input[name="project_id"]').value;
    const requestId = form.querySelector('input[name="request_id"]').value;
    const redcapCsrfToken = form.querySelector('input[name="redcap_csrf_token"]').value;
    
    if (!projectId || !requestId) {
      showMessage('Missing required parameters', 'error');
      return;
    }
    
    setLoading(true);
    
    try {
      const data = {
        project_id: form.querySelector('input[name="project_id"]').value,
        request_id: form.querySelector('input[name="request_id"]').value,
        redcap_csrf_token: form.querySelector('input[name="redcap_csrf_token"]').value,
        action
      };
      
      const response = await fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
      });
      
      const result = await response.json();
      
      if (result.success) {
        showMessage(result.message, 'success');
        setTimeout(() => {
          closeIframe();
        }, 1500);
      } else {
        showMessage(result.error || 'An error occurred', 'error');
      }
    } catch (error) {
      showMessage('Network error: ' + error.message, 'error');
    } finally {
      setLoading(false);
    }
  }

  acceptButton.addEventListener('click', () => processRequest('accept'));
  rejectButton.addEventListener('click', () => processRequest('reject'));
</script>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
