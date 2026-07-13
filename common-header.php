<?php
// Common header with institution branding
// Include this at the top of any page after config.php

// Get settings (should be loaded by the page including this)
global $settings, $instName, $instAddress, $logo;
$primaryColor = $settings['primary_color'] ?? '#308a1e';
$secondaryColor = $settings['secondary_color'] ?? '#269c16';
?>

<!-- Institution Top Bar -->
<div class="institution-topbar" style="background: linear-gradient(135deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%);">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <?php if (!empty($logo)): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 45px; margin-right: 12px; border: 2px solid rgba(255,255,255,0.5); border-radius: 5px; padding: 2px; background: rgba(255,255,255,0.2);">
                <?php endif; ?>
                <div>
                    <span class="fw-bold text-white" style="font-size: 1.1rem;"><?php echo htmlspecialchars($instName ?? 'Institution'); ?></span>
                    <?php if (!empty($instAddress)): ?>
                    <span class="text-white-50 ms-2" style="font-size: 0.85rem;">| <?php echo htmlspecialchars($instAddress); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <small class="text-white-50">Annual Performance Evaluation Report System</small>
            </div>
        </div>
    </div>
</div>

<style>
.institution-topbar {
    padding: 10px 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
}
</style>