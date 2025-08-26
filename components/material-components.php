<?php
class MaterialComponents {
    
    /**
     * Generate a material design alert
     */
    public static function alert($message, $type = 'info', $dismissible = true) {
        $alertClass = 'alert alert-' . $type;
        if ($dismissible) {
            $alertClass .= ' alert-dismissible';
        }
        
        $html = '<div class="' . $alertClass . '">';
        
        if ($dismissible) {
            $html .= '<button type="button" class="btn-close" data-dismiss="alert">&times;</button>';
        }
        
        $html .= $message;
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate a material design card
     */
    public static function card($title, $content, $footer = '', $headerActions = '') {
        $html = '<div class="material-card">';
        
        if ($title || $headerActions) {
            $html .= '<div class="card-header">';
            if ($title && $headerActions) {
                $html .= '<div class="d-flex justify-content-between align-items-center">';
                $html .= '<h5 class="mb-0">' . $title . '</h5>';
                $html .= '<div>' . $headerActions . '</div>';
                $html .= '</div>';
            } else {
                $html .= '<h5 class="mb-0">' . $title . '</h5>';
            }
            $html .= '</div>';
        }
        
        $html .= '<div class="card-body">' . $content . '</div>';
        
        if ($footer) {
            $html .= '<div class="card-footer">' . $footer . '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate a modal dialog
     */
    public static function modal($id, $title, $body, $footer = '', $size = '') {
        $modalClass = 'modal-dialog';
        if ($size) {
            $modalClass .= ' modal-' . $size;
        }
        
        $html = '<div class="modal" id="' . $id . '">';
        $html .= '<div class="' . $modalClass . '">';
        
        // Header
        $html .= '<div class="modal-header">';
        $html .= '<h5 class="modal-title">' . $title . '</h5>';
        $html .= '<button type="button" class="modal-close" data-dismiss="modal">&times;</button>';
        $html .= '</div>';
        
        // Body
        $html .= '<div class="modal-body">' . $body . '</div>';
        
        // Footer
        if ($footer) {
            $html .= '<div class="modal-footer">' . $footer . '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate a search form with filters
     */
    public static function searchForm($placeholder = 'Search...', $filters = []) {
        $html = '<div class="material-card mb-4">';
        $html .= '<div class="card-body">';
        $html .= '<form method="GET" class="row">';
        
        // Search input
        $html .= '<div class="col-md-' . (empty($filters) ? '10' : (12 - count($filters) * 2)) . '">';
        $html .= '<input type="text" name="search" class="form-control" placeholder="' . $placeholder . '" value="' . htmlspecialchars($_GET['search'] ?? '') . '">';
        $html .= '</div>';
        
        // Filter dropdowns
        foreach ($filters as $filter) {
            $html .= '<div class="col-md-2">';
            $html .= '<select name="' . $filter['name'] . '" class="form-control">';
            $html .= '<option value="">' . ($filter['label'] ?? 'All') . '</option>';
            
            foreach ($filter['options'] as $value => $label) {
                $selected = ($_GET[$filter['name']] ?? '') === (string)$value ? 'selected' : '';
                $html .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
            }
            
            $html .= '</select>';
            $html .= '</div>';
        }
        
        // Search button
        $html .= '<div class="col-md-2">';
        $html .= '<button type="submit" class="btn btn-primary w-100">Search</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate a data table
     */
    public static function dataTable($headers, $data, $actions = [], $options = []) {
        $tableClass = 'table';
        if (isset($options['striped']) && $options['striped']) {
            $tableClass .= ' table-striped';
        }
        if (isset($options['hover']) && $options['hover']) {
            $tableClass .= ' table-hover';
        }
        
        $html = '<div class="table-responsive">';
        $html .= '<table class="' . $tableClass . '">';
        
        // Headers
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . $header . '</th>';
        }
        if (!empty($actions)) {
            $html .= '<th class="text-right">Actions</th>';
        }
        $html .= '</tr></thead>';
        
        // Body
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $cell . '</td>';
            }
            
            if (!empty($actions)) {
                $html .= '<td class="table-actions">';
                foreach ($actions as $action) {
                    $html .= $action;
                }
                $html .= '</td>';
            }
            
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate breadcrumb navigation
     */
    public static function breadcrumb($items) {
        $html = '<nav aria-label="breadcrumb">';
        $html .= '<ol class="breadcrumb">';
        
        $lastIndex = count($items) - 1;
        foreach ($items as $index => $item) {
            if ($index === $lastIndex) {
                $html .= '<li class="breadcrumb-item active">' . $item['text'] . '</li>';
            } else {
                $html .= '<li class="breadcrumb-item">';
                if (isset($item['url'])) {
                    $html .= '<a href="' . $item['url'] . '">' . $item['text'] . '</a>';
                } else {
                    $html .= $item['text'];
                }
                $html .= '</li>';
            }
        }
        
        $html .= '</ol>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Generate tabs
     */
    public static function tabs($tabs, $activeTab = 0) {
        $html = '<div class="tabs">';
        
        // Tab headers
        $html .= '<ul class="nav nav-tabs">';
        foreach ($tabs as $index => $tab) {
            $activeClass = $index === $activeTab ? 'active' : '';
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link tab-link ' . $activeClass . '" href="#" data-tab="' . $index . '">' . $tab['title'] . '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        
        // Tab content
        $html .= '<div class="tab-content">';
        foreach ($tabs as $index => $tab) {
            $activeClass = $index === $activeTab ? 'active' : '';
            $html .= '<div class="tab-pane ' . $activeClass . '">' . $tab['content'] . '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate progress bar
     */
    public static function progressBar($percentage, $label = '', $color = 'primary') {
        $html = '<div class="progress-container">';
        
        if ($label) {
            $html .= '<div class="d-flex justify-content-between mb-1">';
            $html .= '<span>' . $label . '</span>';
            $html .= '<span>' . $percentage . '%</span>';
            $html .= '</div>';
        }
        
        $html .= '<div class="progress">';
        $html .= '<div class="progress-bar bg-' . $color . '" style="width: ' . $percentage . '%"></div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate statistics cards
     */
    public static function statsGrid($stats, $columns = 4) {
        $colClass = 'col-md-' . (12 / $columns);
        
        $html = '<div class="row dashboard-stats">';
        
        foreach ($stats as $stat) {
            $html .= '<div class="' . $colClass . '">';
            $html .= '<div class="stat-card ' . ($stat['color'] ?? '') . '">';
            $html .= '<div class="stat-number">' . $stat['number'] . '</div>';
            $html .= '<div class="stat-label">' . $stat['label'] . '</div>';
            
            if (isset($stat['icon'])) {
                $html .= '<i class="stat-icon ' . $stat['icon'] . '"></i>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
?>