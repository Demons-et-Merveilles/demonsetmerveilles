<?php

namespace App\Entity\Jdr;

use App\Repository\Jdr\PublishedJdrRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * JDR (ou invité / animation) publié sur la page publique "JDRs et Invités".
 * Remplace l'ancien fichier data/jdr.json.
 */
#[ORM\Entity(repositoryClass: PublishedJdrRepository::class)]
#[ORM\Table(name: 'jdr_published')]
class PublishedJdr
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: JdrType::class)]
    private JdrType $type = JdrType::Jdr;

    #[ORM\Column(length: 20, enumType: JdrFormat::class)]
    private JdrFormat $format = JdrFormat::Simple;

    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 150, maxMessage: '150 caractères maximum.')]
    #[ORM\Column(length: 150)]
    private string $title = '';

    #[Assert\Length(max: 5000, maxMessage: '5000 caractères maximum.')]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // Chemin de l'image relatif au dossier public/ (ex : uploads/jdr/ab12cd34.png ou images/jdr/x.png)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    // MJ lié à ce JDR (null si aucun, ou si le JDR n'a pas de MJ référencé)
    #[ORM\ManyToOne(targetEntity: GameMaster::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GameMaster $gameMaster = null;

    // Texte libre affiché à la place du MJ (nom d'un invité, d'un animateur...)
    #[Assert\Length(max: 200, maxMessage: '200 caractères maximum.')]
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $subtitle = null;

    #[Assert\Url(message: 'Adresse invalide : elle doit commencer par http:// ou https://.', protocols: ['http', 'https'])]
    #[Assert\Length(max: 500, maxMessage: '500 caractères maximum.')]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    #[Assert\Length(max: 150, maxMessage: '150 caractères maximum.')]
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $linkLabel = null;

    // Horaire affiché sur la carte (ex : "Samedi 14h")
    #[Assert\Length(max: 200, maxMessage: '200 caractères maximum.')]
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $schedule = null;

    // Ordre d'affichage sur la page publique (le plus petit en premier)
    #[Assert\Range(min: 0, max: 9999, notInRangeMessage: 'Entrez un nombre entre {{ min }} et {{ max }}.')]
    #[ORM\Column]
    private int $displayOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): JdrType
    {
        return $this->type;
    }

    public function setType(JdrType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFormat(): JdrFormat
    {
        return $this->format;
    }

    public function setFormat(JdrFormat $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getGameMaster(): ?GameMaster
    {
        return $this->gameMaster;
    }

    public function setGameMaster(?GameMaster $gameMaster): static
    {
        $this->gameMaster = $gameMaster;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getLinkLabel(): ?string
    {
        return $this->linkLabel;
    }

    public function setLinkLabel(?string $linkLabel): static
    {
        $this->linkLabel = $linkLabel;

        return $this;
    }

    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    public function setSchedule(?string $schedule): static
    {
        $this->schedule = $schedule;

        return $this;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    /**
     * Nom à afficher sous la description : le MJ lié, sinon le texte libre, sinon vide.
     */
    public function getMasteringName(): string
    {
        return $this->gameMaster?->getName() ?? $this->subtitle ?? '';
    }
}
