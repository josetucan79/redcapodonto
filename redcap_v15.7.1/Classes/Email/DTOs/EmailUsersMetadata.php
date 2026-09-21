<?php
namespace Vanderbilt\REDCap\Classes\Email\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

class  EmailUsersMetadata extends DTO
{

    const PAGE_START = 1;
    const PER_PAGE = 50;

    private $partial_total = 0;
    private $page = 0;
    private $perPage = 0;
    private $total = 0;
    private $overallTotal = 0;

    public function getPartialTotal(): int { return $this->partial_total; }
    public function getPage(): int { return $this->page; }
    public function getPerPage(): int { return $this->perPage; }
    public function getNextPage(): ?int { return $this->getPage() < $this->getTotalPages() ? $this->getPage()+1 : null; }
    public function getTotal(): int { return $this->total; }
    public function getOverallTotal(): int { return $this->overallTotal; }
    public function getTotalPages(): int { return $this->calcTotalPages($this->total, $this->perPage); }

    public function setPartialTotal(int $value): void { $this->partial_total = $value; }
    public function setPage(int $value): void { $this->page = $value; }
    public function setPerPage(int $value): void { $this->perPage = $value; }
    public function setTotal(int $value): void { $this->total = $value; }
    public function setOverallTotal(int $value): void { $this->overallTotal = $value; }


    private function calcTotalPages($total, $perPage) {
        if($perPage === 0) return $total;
        return ceil($total / $perPage);
    }

    public function getData()
    {
        $data = [
            'partial_total'     => $this->getPartialTotal(),
            'page'              => $this->getPage(),
            'per_page'           => $this->getPerPage(),
            'total'             => $this->getTotal(),
            'next_page'         => $this->getNextPage(),
            'total_pages'       => $this->getTotalPages(),
            'overall_total'       => $this->getOverallTotal(),
        ];
        return $data;
    }


}