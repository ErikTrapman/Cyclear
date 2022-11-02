<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * App\Entity\Ploeg
 *
 * @ORM\Table(name="ploeg")
 * @ORM\Entity(repositoryClass="App\Repository\PloegRepository")
 * @Serializer\ExclusionPolicy("all")
 */
class Ploeg
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @Serializer\Expose
     * @Serializer\Groups({"small","medium"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="ploeg")
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="naam", type="string", length=255)
     *
     * @Serializer\Expose
     * @Serializer\Groups({"small","medium"})
     */
    private $naam;

    /**
     * @var string
     *
     * @ORM\Column(name="afkorting", type="string", length=6)
     *
     * @Serializer\Expose
     * @Serializer\Groups({"small","medium"})
     */
    private $afkorting;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Seizoen")
     */
    private $seizoen;

    private $punten;

    /**
     * @ORM\Column(type="text", nullable=true, length=16777215)
     */
    private $memo;

    /**
     * @return the $user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param field_type $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set naam
     *
     * @param string $naam
     */
    public function setNaam($naam)
    {
        $this->naam = $naam;
    }

    /**
     * Get naam
     *
     * @return string
     */
    public function getNaam()
    {
        return $this->naam;
    }

    /**
     * @return the $afkorting
     */
    public function getAfkorting()
    {
        return $this->afkorting;
    }

    /**
     * @param string $afkorting
     */
    public function setAfkorting($afkorting)
    {
        $this->afkorting = $afkorting;
    }

    public function __toString()
    {
        return (string)$this->getAfkorting();
    }

    /**
     * @return Seizoen
     */
    public function getSeizoen()
    {
        return $this->seizoen;
    }

    public function setSeizoen($seizoen)
    {
        $this->seizoen = $seizoen;
    }

    public function getNaamWithSeizoen()
    {
        return $this->getNaam() . ' [' . $this->getSeizoen()->getIdentifier() . ']';
    }

    /**
     * @Serializer\Groups({"small","medium"})
     * @Serializer\VirtualProperty()
     */
    public function getPunten()
    {
        return $this->punten;
    }

    public function setPunten($punten)
    {
        $this->punten = $punten;
    }

    /**
     * @return mixed
     */
    public function getMemo()
    {
        return $this->memo;
    }

    /**
     * @param mixed $memo
     */
    public function setMemo($memo)
    {
        $this->memo = $memo;
    }
}
