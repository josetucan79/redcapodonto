<?php
/**
 * This page clears Doctrine metadata cache and regenerates proxy classes when visited
 */

// Include necessary REDCap files for authentication and setup
include 'header.php';

use Vanderbilt\REDCap\Classes\ORM\EntityManagerBuilder;
use Vanderbilt\REDCap\Classes\Utility\SessionDataUtils;

// Ensure only users with the appropriate permissions can access this page
if (!SUPER_USER) {
    exit("You do not have permission to access this page.");
}

// Process if POST request is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $reason = $_POST['reason'] ?? false;

        if (!$reason) {
            throw new Exception("A reason must be provided", 401);
        }

        $reason = strip_tags($reason);
        
        // Create builder with forced regeneration
        $builder = EntityManagerBuilder::create()
            ->setDevMode(true)  // Force dev mode
            ->setForceProxyRegeneration(true);  // Force proxy regeneration
        
        // Build EntityManager to trigger cache clearing and proxy regeneration
        $entityManager = $builder->build();
        
        // Log the action
        Logging::logEvent(
            $sql = '',
            $table = '',
            $event = 'MANAGE',
            $identifier = USERID,
            $display = '',
            $descrip = 'Cleared Doctrine metadata cache and regenerated proxies',
            $change_reason = $reason
        );
        
        // Set success message
        flash('alert-success', 'Doctrine metadata cache and proxy classes have been successfully cleared and regenerated.');
    } catch (\Throwable $th) {
        // Set error message
        flash('alert-danger', $th->getMessage());
    } finally {
        // Redirect to same page (GET)
        redirect(previousURL());
    }
}
?>

<div class="container">
    <h4>
        <i class="fas fa-broom fa-fw"></i>
        <span>Doctrine Cache Cleaner</span>
    </h4>
    
    <?= SessionDataUtils::getAlerts() ?>
    
    <form action="" method="POST" class="border rounded p-2" id="clear-cache-form">
        <p>Click the button below to clear Doctrine metadata cache and regenerate proxy classes:</p>
        
        <div class="form-group mb-3">
            <label for="reason">Reason for clearing cache:</label>
            <input type="text" class="form-control" id="reason" name="reason" required 
                   placeholder="e.g., Updated entity definitions, Troubleshooting mapping errors">
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-sm">Clear Metadata Cache & Regenerate Proxies</button>
        </div>
    </form>
    
    <div class="mt-4">
        <h4>What this does:</h4>
        <ul>
            <li>Clears all Doctrine metadata cache</li>
            <li>Removes all existing proxy classes</li>
            <li>Regenerates all proxy classes</li>
            <li>Resets the version tracking file</li>
        </ul>
        
        <h4>When to use:</h4>
        <ul>
            <li>During REDCap development</li>
            <li>After updating entity definitions</li>
            <li>If you experience errors related to entity mapping</li>
            <li>If Doctrine functionality appears broken</li>
        </ul>
    </div>
</div>

<?php
include 'footer.php';