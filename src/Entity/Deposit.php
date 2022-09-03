<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Nines\UtilBundle\Entity\AbstractEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Deposit.
 *
 * @ORM\Table(name="deposit", indexes={
 *     @ORM\Index(columns={"deposit_uuid", "url"}, flags={"fulltext"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\DepositRepository")
 */
class Deposit extends AbstractEntity
{
    /**
     * Default OJS version.
     *
     * The journal version was added to the PKP PLN plugin in OJS version 3. If
     * a deposit doesn't have a version attribute, then assume it is OJS 2.4.8.
     */
    public const DEFAULT_JOURNAL_VERSION = '2.4.8';

    /**
     * The journal that sent this deposit.
     *
     * @ORM\ManyToOne(targetEntity="Journal", inversedBy="deposits")
     * @ORM\JoinColumn(name="journal_id", referencedColumnName="id")
     */
    private Journal $journal;

    /**
     * The AuContainer that holds this deposit.
     *
     * @ORM\ManyToOne(targetEntity="AuContainer", inversedBy="deposits")
     * @ORM\JoinColumn(name="au_container_id", referencedColumnName="id", nullable=true)
     */
    private ?AuContainer $auContainer = null;

    /**
     * The version of OJS that made the deposit and created the export file.
     *
     * The default is 2.4.8. If annotations made use of class constants, it would use
     * self::DEFAULT_JOURNAL_VERSION.
     *
     * @ORM\Column(type="string", length=15, nullable=false, options={"default": "2.4.8"})
     */
    private string $journalVersion;

    /**
     * Serialized list of licensing terms as reported in the ATOM deposit.
     *
     * @var array<string,string>
     * @ORM\Column(type="array")
     */
    private array $license;

    /**
     * Mime type from the deposit.
     *
     * Bagit doesn't understand compressed files that don't have a file
     * extension. So set the file type, and build file names from that.
     *
     * @ORM\Column(type="string", nullable=true);
     */
    private ?string $fileType = null;

    /**
     * Deposit UUID, as generated by the PLN plugin in OJS.
     *
     * @Assert\Uuid
     * @ORM\Column(type="string", length=36, nullable=false, unique=true)
     */
    private string $depositUuid;

    /**
     * The deposit action (add, edit).
     *
     * @ORM\Column(type="string", nullable=false)
     */
    private string $action;

    /**
     * The issue volume number.
     *
     * @ORM\Column(type="string", nullable=false)
     */
    private string $volume;

    /**
     * The issue number for the deposit.
     *
     * @ORM\Column(type="string")
     */
    private string $issue;

    /**
     * Publication date of the deposit content.
     *
     * @ORM\Column(type="date")
     */
    private DateTimeInterface $pubDate;

    /**
     * The checksum type for the deposit (SHA1, MD5).
     *
     * @ORM\Column(type="string")
     */
    private string $checksumType;

    /**
     * The checksum value, in hex.
     *
     * @Assert\Regex("/^[0-9a-f]+$/");
     * @ORM\Column(type="string")
     */
    private string $checksumValue;

    /**
     * The source URL for the deposit. This may be a very large string.
     *
     * @Assert\Url
     * @ORM\Column(type="string", length=2048)
     */
    private string $url;

    /**
     * Size of the deposit, in bytes.
     *
     * @ORM\Column(type="integer")
     */
    private int $size;

    /**
     * Current processing state.
     *
     * @ORM\Column(type="string")
     */
    private string $state;

    /**
     * List of errors that occured while processing.
     *
     * @var string[]
     * @ORM\Column(type="array", nullable=false)
     */
    private array $errorLog;

    /**
     * State of the deposit in LOCKSSOMatic.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $plnState = null;

    /**
     * Size of the processed package file, ready for deposit to LOCKSS.
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $packageSize = null;

    /**
     * Processed package checksum type.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $packageChecksumType = null;

    /**
     * Checksum for the processed package file.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $packageChecksumValue = null;

    /**
     * Date the deposit was sent to LOCKSSOmatic or the PLN.
     *
     * @ORM\Column(type="date", nullable=true)
     */
    private ?DateTimeInterface $depositDate = null;

    /**
     * URL for the deposit receipt in LOCKSSOMatic.
     *
     * @Assert\Url
     * @ORM\Column(type="string", length=2048, nullable=true)
     */
    private ?string $depositReceipt = null;

    /**
     * Processing log for this deposit.
     *
     * @ORM\Column(type="text")
     */
    private string $processingLog;

    /**
     * Number of times the the server has attempted to harvest the deposit.
     *
     * @ORM\Column(type="integer")
     */
    private int $harvestAttempts;

    /**
     * Construct a deposit.
     */
    public function __construct()
    {
        parent::__construct();
        $this->license = [];
        $this->processingLog = '';
        $this->state = 'depositedByJournal';
        $this->errorLog = [];
        $this->harvestAttempts = 0;
        $this->journalVersion = self::DEFAULT_JOURNAL_VERSION;
    }

    /**
     * Return the deposit UUID.
     */
    public function __toString(): string
    {
        return $this->getDepositUuid();
    }

    /**
     * Set journalVersion.
     */
    public function setJournalVersion(string $journalVersion): static
    {
        $this->journalVersion = $journalVersion;

        return $this;
    }

    /**
     * Get journalVersion.
     */
    public function getJournalVersion(): string
    {
        return $this->journalVersion;
    }

    /**
     * Set license.
     * @param array<string,string> $license
     */
    public function setLicense(array $license): static
    {
        $this->license = $license;

        return $this;
    }

    /**
     * Add a bit of licensing information to a deposit.
     */
    public function addLicense(string $key, string $value): static
    {
        if (trim($value)) {
            $this->license[$key] = trim($value);
        }

        return $this;
    }

    /**
     * Get license.
     * @return array<string,string>
     */
    public function getLicense(): array
    {
        return $this->license;
    }

    /**
     * Set fileType.
     */
    public function setFileType(?string $fileType): static
    {
        $this->fileType = $fileType;

        return $this;
    }

    /**
     * Get fileType.
     */
    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    /**
     * Set depositUuid.
     *
     * UUIDs are stored and returned in upper case letters.
     */
    public function setDepositUuid(string $depositUuid): static
    {
        $this->depositUuid = strtoupper($depositUuid);

        return $this;
    }

    /**
     * Get depositUuid.
     */
    public function getDepositUuid(): string
    {
        return $this->depositUuid;
    }

    /**
     * Get received.
     */
    public function getReceived(): DateTimeInterface
    {
        return $this->created;
    }

    /**
     * Set action.
     */
    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get action.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Set volume.
     */
    public function setVolume(string $volume): static
    {
        $this->volume = $volume;

        return $this;
    }

    /**
     * Get volume.
     */
    public function getVolume(): string
    {
        return $this->volume;
    }

    /**
     * Set issue.
     */
    public function setIssue(string $issue): static
    {
        $this->issue = $issue;

        return $this;
    }

    /**
     * Get issue.
     */
    public function getIssue(): string
    {
        return $this->issue;
    }

    /**
     * Set pubDate.
     */
    public function setPubDate(DateTimeInterface $pubDate): static
    {
        $this->pubDate = $pubDate;

        return $this;
    }

    /**
     * Get pubDate.
     */
    public function getPubDate(): DateTimeInterface
    {
        return $this->pubDate;
    }

    /**
     * Set checksumType.
     */
    public function setChecksumType(string $checksumType): static
    {
        $this->checksumType = strtolower($checksumType);

        return $this;
    }

    /**
     * Get checksumType.
     */
    public function getChecksumType(): string
    {
        return $this->checksumType;
    }

    /**
     * Set checksumValue.
     */
    public function setChecksumValue(string $checksumValue): static
    {
        $this->checksumValue = strtoupper($checksumValue);

        return $this;
    }

    /**
     * Get checksumValue.
     */
    public function getChecksumValue(): string
    {
        return $this->checksumValue;
    }

    /**
     * Set url.
     */
    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set size.
     */
    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Get size.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Set state.
     */
    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Set errorLog.
     * @param string[] $errorLog
     */
    public function setErrorLog(array $errorLog): static
    {
        $this->errorLog = $errorLog;

        return $this;
    }

    /**
     * Get errorLog.
     * @return string[]
     */
    public function getErrorLog(?string $delim = null): array|string
    {
        if ($delim) {
            return implode($delim, $this->errorLog);
        }

        return $this->errorLog;
    }

    /**
     * Add a message to the error log.
     */
    public function addErrorLog(string $error): static
    {
        $this->errorLog[] = $error;

        return $this;
    }

    /**
     * Set plnState.
     */
    public function setPlnState(?string $plnState): static
    {
        $this->plnState = $plnState;

        return $this;
    }

    /**
     * Get plnState.
     */
    public function getPlnState(): ?string
    {
        return $this->plnState;
    }

    /**
     * Set packageSize.
     */
    public function setPackageSize(?int $packageSize): static
    {
        $this->packageSize = $packageSize;

        return $this;
    }

    /**
     * Get packageSize.
     */
    public function getPackageSize(): ?int
    {
        return $this->packageSize;
    }

    /**
     * Set packageChecksumType.
     */
    public function setPackageChecksumType(?string $packageChecksumType): static
    {
        if ($packageChecksumType) {
            $this->packageChecksumType = strtolower($packageChecksumType);
        }

        return $this;
    }

    /**
     * Get packageChecksumType.
     */
    public function getPackageChecksumType(): ?string
    {
        return $this->packageChecksumType;
    }

    /**
     * Set packageChecksumValue.
     */
    public function setPackageChecksumValue(?string $packageChecksumValue): static
    {
        if ($packageChecksumValue) {
            $this->packageChecksumValue = strtoupper($packageChecksumValue);
        }

        return $this;
    }

    /**
     * Get packageChecksumValue.
     */
    public function getPackageChecksumValue(): ?string
    {
        return $this->packageChecksumValue;
    }

    /**
     * Set depositDate.
     */
    public function setDepositDate(?DateTimeInterface $depositDate): static
    {
        $this->depositDate = $depositDate;

        return $this;
    }

    /**
     * Get depositDate.
     */
    public function getDepositDate(): ?DateTimeInterface
    {
        return $this->depositDate;
    }

    /**
     * Set depositReceipt.
     */
    public function setDepositReceipt(?string $depositReceipt): static
    {
        $this->depositReceipt = $depositReceipt;

        return $this;
    }

    /**
     * Get depositReceipt.
     */
    public function getDepositReceipt(): ?string
    {
        return $this->depositReceipt;
    }

    /**
     * Set processingLog.
     */
    public function setProcessingLog(string $processingLog): static
    {
        $this->processingLog = $processingLog;

        return $this;
    }

    /**
     * Get processingLog.
     */
    public function getProcessingLog(): string
    {
        return $this->processingLog;
    }

    /**
     * Append to the processing history.
     */
    public function addToProcessingLog(string $content): static
    {
        $date = date(DateTimeInterface::ATOM);
        $this->processingLog .= "{$date}\n{$content}\n\n";

        return $this;
    }

    /**
     * Set harvestAttempts.
     */
    public function setHarvestAttempts(int $harvestAttempts): static
    {
        $this->harvestAttempts = $harvestAttempts;

        return $this;
    }

    /**
     * Get harvestAttempts.
     */
    public function getHarvestAttempts(): int
    {
        return $this->harvestAttempts;
    }

    /**
     * Set journal.
     */
    public function setJournal(Journal $journal = null): static
    {
        $this->journal = $journal;

        return $this;
    }

    /**
     * Get journal.
     */
    public function getJournal(): Journal
    {
        return $this->journal;
    }

    /**
     * Set auContainer.
     */
    public function setAuContainer(AuContainer $auContainer = null): static
    {
        $this->auContainer = $auContainer;

        return $this;
    }

    /**
     * Get auContainer.
     */
    public function getAuContainer(): AuContainer
    {
        return $this->auContainer;
    }
}
