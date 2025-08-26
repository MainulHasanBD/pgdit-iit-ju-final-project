<?php
class Pagination {
    // Paginate the records
    public static function paginate($totalRecords, $page, $recordsPerPage = 10) {
        // Calculate total pages
        $totalPages = ceil($totalRecords / $recordsPerPage);

        // Calculate the offset for the current page
        $offset = ($page - 1) * $recordsPerPage;

        // Return pagination data
        return [
            'total_pages' => $totalPages,
            'offset' => $offset
        ];
    }

    // Generate the pagination HTML
    public static function generatePaginationHTML($pagination, $baseUrl) {
        $html = '<nav aria-label="Page navigation"><ul class="pagination">';

        // Previous page link
        if ($pagination['total_pages'] > 1 && $pagination['total_pages'] > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=1">First</a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($pagination['current_page'] - 1) . '">Previous</a></li>';
        }

        // Page number links
        for ($i = 1; $i <= $pagination['total_pages']; $i++) {
            $html .= '<li class="page-item ' . ($i == $pagination['current_page'] ? 'active' : '') . '">
                        <a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a>
                    </li>';
        }

        // Next page link
        if ($pagination['total_pages'] > 1 && $pagination['total_pages'] > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($pagination['current_page'] + 1) . '">Next</a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $pagination['total_pages'] . '">Last</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }
}
?>
