<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AcmePhp\Cli\Repository;

use AcmePhp\Cli\Exception\AcmeCliException;
use AcmePhp\Cli\Serializer\PemEncoder;
use AcmePhp\Core\Protocol\AuthorizationChallenge;
use AcmePhp\Core\Protocol\CertificateOrder;
use AcmePhp\Ssl\Certificate;
use AcmePhp\Ssl\CertificateResponse;
use AcmePhp\Ssl\DistinguishedName;
use AcmePhp\Ssl\KeyPair;
use AcmePhp\Ssl\PrivateKey;
use AcmePhp\Ssl\PublicKey;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class Repository implements RepositoryV2Interface
{
    const PATH_ACCOUNT_KEY_PRIVATE = 'account/key.private.pem';
    const PATH_ACCOUNT_KEY_PUBLIC = 'account/key.public.pem';

    const PATH_DOMAIN_KEY_PUBLIC = 'certs/{domain}/private/key.public.pem';
    const PATH_DOMAIN_KEY_PRIVATE = 'certs/{domain}/private/key.private.pem';
    const PATH_DOMAIN_CERT_CERT = 'certs/{domain}/public/cert.pem';
    const PATH_DOMAIN_CERT_CHAIN = 'certs/{domain}/public/chain.pem';
    const PATH_DOMAIN_CERT_FULLCHAIN = 'certs/{domain}/public/fullchain.pem';
    const PATH_DOMAIN_CERT_COMBINED = 'certs/{domain}/private/combined.pem';

    const PATH_CACHE_AUTHORIZATION_CHALLENGE = 'var/{domain}/authorization_challenge.json';
    const PATH_CACHE_DISTINGUISHED_NAME = 'var/{domain}/distinguished_name.json';
    const PATH_CACHE_CERTIFICATE_ORDER = 'var/{domains}/certificate_order.json';

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var FilesystemInterface
     */
    private $master;

    /**
     * @var FilesystemInterface
     */
    private $backup;

    /**
     * @var bool
     */
    private $enableBackup;

    /**
     * @param SerializerInterface $serializer
     * @param FilesystemInterface $master
     * @param FilesystemInterface $backup
     * @param bool                $enableBackup
     */
    public function __construct(SerializerInterface $serializer, FilesystemInterface $master, FilesystemInterface $backup, $enableBackup)
    {
        $this->serializer = $serializer;
        $this->master = $master;
        $this->backup = $backup;
        $this->enableBackup = $enableBackup;
    }

    /**
     * {@inheritdoc}
     */
    public function storeCertificateResponse(CertificateResponse $certificateResponse)
    {
        $distinguishedName = $certificateResponse->getCertificateRequest()->getDistinguishedName();
        $domain = $distinguishedName->getCommonName();

        $this->storeDomainKeyPair($domain, $certificateResponse->getCertificateRequest()->getKeyPair());
        $this->storeDomainDistinguishedName($domain, $distinguishedName);
        $this->storeDomainCertificate($domain, $certificateResponse->getCertificate());
    }

    /**
     * {@inheritdoc}
     */
    public function storeAccountKeyPair(KeyPair $keyPair)
    {
        try {
            $this->save(
                self::PATH_ACCOUNT_KEY_PUBLIC,
                $this->serializer->serialize($keyPair->getPublicKey(), PemEncoder::FORMAT)
            );

            $this->save(
                self::PATH_ACCOUNT_KEY_PRIVATE,
                $this->serializer->serialize($keyPair->getPrivateKey(), PemEncoder::FORMAT)
            );
        } catch (\Exception $e) {
            throw new AcmeCliException('Storing of account key pair failed', $e);
        }
    }

    private function getPathForDomain($path, $domain)
    {
        return strtr($path, ['{domain}' => $this->normalizeDomain($domain)]);
    }

    private function getPathForDomainList($path, array $domains)
    {
        return strtr($path, ['{domains}' => $this->normalizeDomainList($domains)]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasAccountKeyPair()
    {
        return $this->master->has(self::PATH_ACCOUNT_KEY_PRIVATE);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAccountKeyPair()
    {
        try {
            $publicKeyPem = $this->master->read(self::PATH_ACCOUNT_KEY_PUBLIC);
            $privateKeyPem = $this->master->read(self::PATH_ACCOUNT_KEY_PRIVATE);

            return new KeyPair(
                $this->serializer->deserialize($publicKeyPem, PublicKey::class, PemEncoder::FORMAT),
                $this->serializer->deserialize($privateKeyPem, PrivateKey::class, PemEncoder::FORMAT)
            );
        } catch (\Exception $e) {
            throw new AcmeCliException('Loading of account key pair failed', $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function storeDomainKeyPair($domain, KeyPair $keyPair)
    {
        try {
            $this->save(
                $this->getPathForDomain(self::PATH_DOMAIN_KEY_PUBLIC, $domain),
                $this->serializer->serialize($keyPair->getPublicKey(), PemEncoder::FORMAT)
            );

            $this->save(
                $this->getPathForDomain(self::PATH_DOMAIN_KEY_PRIVATE, $domain),
                $this->serializer->serialize($keyPair->getPrivateKey(), PemEncoder::FORMAT)
            );
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Storing of domain %s key pair failed', $domain), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasDomainKeyPair($domain)
    {
        return $this->master->has($this->getPathForDomain(self::PATH_DOMAIN_KEY_PRIVATE, $domain));
    }

    /**
     * {@inheritdoc}
     */
    public function loadDomainKeyPair($domain)
    {
        try {
            $publicKeyPem = $this->master->read($this->getPathForDomain(self::PATH_DOMAIN_KEY_PUBLIC, $domain));
            $privateKeyPem = $this->master->read($this->getPathForDomain(self::PATH_DOMAIN_KEY_PRIVATE, $domain));

            return new KeyPair(
                $this->serializer->deserialize($publicKeyPem, PublicKey::class, PemEncoder::FORMAT),
                $this->serializer->deserialize($privateKeyPem, PrivateKey::class, PemEncoder::FORMAT)
            );
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Loading of domain %s key pair failed', $domain), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function storeDomainAuthorizationChallenge($domain, AuthorizationChallenge $authorizationChallenge)
    {
        try {
            $this->save(
                $this->getPathForDomain(self::PATH_CACHE_AUTHORIZATION_CHALLENGE, $domain),
                $this->serializer->serialize($authorizationChallenge, JsonEncoder::FORMAT)
            );
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Storing of domain %s authorization challenge failed', $domain), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasDomainAuthorizationChallenge($domain)
    {
        return $this->master->has($this->getPathForDomain(self::PATH_CACHE_AUTHORIZATION_CHALLENGE, $domain));
    }

    /**
     * {@inheritdoc}
     */
    public function loadDomainAuthorizationChallenge($domain)
    {
        try {
            $json = $this->master->read($this->getPathForDomain(self::PATH_CACHE_AUTHORIZATION_CHALLENGE, $domain));

            return $this->serializer->deserialize($json, AuthorizationChallenge::class, JsonEncoder::FORMAT);
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Loading of domain %s authorization challenge failed', $domain), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function storeDomainDistinguishedName($domain, DistinguishedName $distinguishedName)
    {
        try {
            $this->save(
                $this->getPathForDomain(self::PATH_CACHE_DISTINGUISHED_NAME, $domain),
                $this->serializer->serialize($distinguishedName, JsonEncoder::FORMAT)
            );
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Storing of domain %s distinguished name failed', $domain), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasDomainDistinguishedName($domain)
    {
        return $this->master->has($this->getPathForDomain(self::PATH_CACHE_DISTINGUISHED_NAME, $domain));
    }

    /**
     * {@inheritdoc}
     */
    public function loadDomainDistinguishedName($domain)
    {
        try {
            $json = $this->master->read($this->getPathForDomain(self::PATH_CACHE_DISTINGUISHED_NAME, $domain));

            return $this->serializer->deserialize($json, DistinguishedName::class, JsonEncoder::FORMAT);
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Loading of domain %s distinguished name failed', $domain), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function storeDomainCertificate($domain, Certificate $certificate)
    {
        // Simple certificate
        $certPem = $this->serializer->serialize($certificate, PemEncoder::FORMAT);

        // Issuer chain
        $issuerChain = [];
        $issuerCertificate = $certificate->getIssuerCertificate();

        while (null !== $issuerCertificate) {
            $issuerChain[] = $this->serializer->serialize($issuerCertificate, PemEncoder::FORMAT);
            $issuerCertificate = $issuerCertificate->getIssuerCertificate();
        }

        $chainPem = implode("\n", $issuerChain);

        // Full chain
        $fullChainPem = $certPem.$chainPem;

        // Combined
        $keyPair = $this->loadDomainKeyPair($domain);
        $combinedPem = $fullChainPem.$this->serializer->serialize($keyPair->getPrivateKey(), PemEncoder::FORMAT);

        // Save
        $this->save($this->getPathForDomain(self::PATH_DOMAIN_CERT_CERT, $domain), $certPem);
        $this->save($this->getPathForDomain(self::PATH_DOMAIN_CERT_CHAIN, $domain), $chainPem);
        $this->save($this->getPathForDomain(self::PATH_DOMAIN_CERT_FULLCHAIN, $domain), $fullChainPem);
        $this->save($this->getPathForDomain(self::PATH_DOMAIN_CERT_COMBINED, $domain), $combinedPem);
    }

    /**
     * {@inheritdoc}
     */
    public function hasDomainCertificate($domain)
    {
        return $this->master->has($this->getPathForDomain(self::PATH_DOMAIN_CERT_FULLCHAIN, $domain));
    }

    /**
     * {@inheritdoc}
     */
    public function loadDomainCertificate($domain)
    {
        try {
            $pems = explode('-----BEGIN CERTIFICATE-----', $this->master->read($this->getPathForDomain(self::PATH_DOMAIN_CERT_FULLCHAIN, $domain)));
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Loading of domain %s certificate failed', $domain), $e);
        }

        $pems = array_map(function ($item) {
            return trim(str_replace('-----END CERTIFICATE-----', '', $item));
        }, $pems);
        array_shift($pems);
        $pems = array_reverse($pems);

        $certificate = null;

        foreach ($pems as $pem) {
            $certificate = new Certificate(
                "-----BEGIN CERTIFICATE-----\n".$pem."\n-----END CERTIFICATE-----",
                $certificate
            );
        }

        return $certificate;
    }

    /**
     * {@inheritdoc}
     */
    public function storeCertificateOrder(array $domains, CertificateOrder $order)
    {
        try {
            $this->save(
                $this->getPathForDomainList(self::PATH_CACHE_CERTIFICATE_ORDER, $domains),
                $this->serializer->serialize($order, JsonEncoder::FORMAT)
            );
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Storing of domains %s certificate order failed', implode(', ', $domains)), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasCertificateOrder(array $domains)
    {
        return $this->master->has($this->getPathForDomainList(self::PATH_CACHE_CERTIFICATE_ORDER, $domains));
    }

    /**
     * {@inheritdoc}
     */
    public function loadCertificateOrder(array $domains)
    {
        try {
            $json = $this->master->read($this->getPathForDomainList(self::PATH_CACHE_CERTIFICATE_ORDER, $domains));

            return $this->serializer->deserialize($json, CertificateOrder::class, JsonEncoder::FORMAT);
        } catch (\Exception $e) {
            throw new AcmeCliException(sprintf('Loading of domains %s certificate order failed', implode(', ', $domains)), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save($path, $content, $visibility = self::VISIBILITY_PRIVATE)
    {
        if (!$this->master->has($path)) {
            // File creation: remove from backup if it existed and warm-up both master and backup
            $this->createAndBackup($path, $content);
        } else {
            // File update: backup before writing
            $this->backupAndUpdate($path, $content);
        }

        if ($this->enableBackup) {
            $this->backup->setVisibility($path, $visibility);
        }

        $this->master->setVisibility($path, $visibility);
    }

    private function createAndBackup($path, $content)
    {
        if ($this->enableBackup) {
            if ($this->backup->has($path)) {
                $this->backup->delete($path);
            }

            $this->backup->write($path, $content);
        }

        $this->master->write($path, $content);
    }

    private function backupAndUpdate($path, $content)
    {
        if ($this->enableBackup) {
            $oldContent = $this->master->read($path);

            if (false !== $oldContent) {
                if ($this->backup->has($path)) {
                    $this->backup->update($path, $oldContent);
                } else {
                    $this->backup->write($path, $oldContent);
                }
            }
        }

        $this->master->update($path, $content);
    }

    private function normalizeDomain($domain)
    {
        return $domain;
    }

    private function normalizeDomainList(array $domains)
    {
        $normalizedDomains = array_unique(array_map([$this, 'normalizeDomain'], $domains));
        sort($normalizedDomains);

        return (isset($domains[0]) ? $this->normalizeDomain($domains[0]) : '-').'/'.sha1(json_encode($normalizedDomains));
    }
}
