<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Slug;

#[ORM\Entity(repositoryClass: \App\Repository\SeizoenRepository::class)]
#[ORM\Table(name: 'seizoen')]
class Seizoen
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column]
    private $identifier;

    #[Slug(fields: ['identifier'])]
    #[ORM\Column(length: 128, unique: true)]
    private $slug;

    #[ORM\Column(type: 'boolean')]
    private $closed = false;

    #[ORM\Column(type: 'boolean')]
    private $current = false;

    #[ORM\Column(type: 'date', nullable: true)]
    private $start;

    #[ORM\Column(type: 'date', nullable: true)]
    private $end;

    #[ORM\Column(type: 'integer', nullable: true, name: 'maxPointsPerRider')]
    private $maxPointsPerRider;

    #[ORM\Column(type: 'integer', nullable: true, options: ['default' => 0])]
    private $maxTransfers;

    public function getId()
    {
        return $this->id;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getClosed()
    {
        return $this->closed;
    }

    public function setClosed(bool $closed): void
    {
        $this->closed = $closed;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function isCurrent(): bool
    {
        return (bool)$this->current;
    }

    public function setCurrent(bool $current): void
    {
        $this->current = $current;
    }

    public function getStart()
    {
        return $this->start;
    }

    public function setStart($start): void
    {
        $this->start = $start;
    }

    public function getEnd()
    {
        return $this->end;
    }

    public function setEnd($end): void
    {
        $this->end = $end;
    }

    public function getMaxPointsPerRider()
    {
        return $this->maxPointsPerRider;
    }

    public function setMaxPointsPerRider(int $maxPointsPerRider): void
    {
        $this->maxPointsPerRider = $maxPointsPerRider;
    }

    public function getMaxTransfers()
    {
        return $this->maxTransfers;
    }

    public function setMaxTransfers(int $maxTransfers): void
    {
        $this->maxTransfers = $maxTransfers;
    }

    public function __toString()
    {
        return $this->identifier;
    }
}
