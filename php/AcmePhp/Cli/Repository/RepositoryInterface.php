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
use AcmePhp\Core\Protocol\AuthorizationChallenge;
use AcmePhp\Ssl\Certificate;
use AcmePhp\Ssl\CertificateResponse;
use AcmePhp\Ssl\DistinguishedName;
use AcmePhp\Ssl\KeyPair;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface RepositoryInterface
{
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PRIVATE = 'private';

    /**
     * Extract important elements from the given certificate response and store them
     * in the repository.
     *
     * This method will use the distinguished name common name as a domain to store:
     *      - the key pair
     *      - the certificate request
     *      - the certificate
     *
     * @param CertificateResponse $certificateResponse
     *
     * @throws AcmeCliException
     */
    public function storeCertificateResponse(CertificateResponse $certificateResponse);

    /**
     * Store a given key pair as the account key pair (the global key pair used to
     * interact with the ACME server).
     *
     * @param KeyPair $keyPair
     *
     * @throws AcmeCliException
     */
    public function storeAccountKeyPair(KeyPair $keyPair);

    /**
     * Check if there is an account key pair in the repository.
     *
     * @return bool
     */
    public function hasAccountKeyPair();

    /**
     * Load the account key pair.
     *
     * @throws AcmeCliException
     *
     * @return KeyPair
     */
    public function loadAccountKeyPair();

    /**
     * Store a given key pair as associated to a given domain.
     *
     * @param string  $domain
     * @param KeyPair $keyPair
     *
     * @throws AcmeCliException
     */
    public function storeDomainKeyPair($domain, KeyPair $keyPair);

    /**
     * Check if there is a key pair associated to the given domain in the repository.
     *
     * @param string $domain
     *
     * @return bool
     */
    public function hasDomainKeyPair($domain);

    /**
     * Load the key pair associated to a given domain.
     *
     * @param string $domain
     *
     * @throws AcmeCliException
     *
     * @return KeyPair
     */
    public function loadDomainKeyPair($domain);

    /**
     * Store a given authorization challenge as associated to a given domain.
     *
     * @param string                 $domain
     * @param AuthorizationChallenge $authorizationChallenge
     *
     * @throws AcmeCliException
     */
    public function storeDomainAuthorizationChallenge($domain, AuthorizationChallenge $authorizationChallenge);

    /**
     * Check if there is an authorization challenge associated to the given domain in the repository.
     *
     * @param string $domain
     *
     * @return bool
     */
    public function hasDomainAuthorizationChallenge($domain);

    /**
     * Load the authorization challenge associated to a given domain.
     *
     * @param string $domain
     *
     * @throws AcmeCliException
     *
     * @return AuthorizationChallenge
     */
    public function loadDomainAuthorizationChallenge($domain);

    /**
     * Store a given distinguished name as associated to a given domain.
     *
     * @param string            $domain
     * @param DistinguishedName $distinguishedName
     *
     * @throws AcmeCliException
     */
    public function storeDomainDistinguishedName($domain, DistinguishedName $distinguishedName);

    /**
     * Check if there is a distinguished name associated to the given domain in the repository.
     *
     * @param string $domain
     *
     * @return bool
     */
    public function hasDomainDistinguishedName($domain);

    /**
     * Load the distinguished name associated to a given domain.
     *
     * @param string $domain
     *
     * @throws AcmeCliException
     *
     * @return DistinguishedName
     */
    public function loadDomainDistinguishedName($domain);

    /**
     * Store a given certificate as associated to a given domain.
     *
     * @param string      $domain
     * @param Certificate $certificate
     *
     * @throws AcmeCliException
     */
    public function storeDomainCertificate($domain, Certificate $certificate);

    /**
     * Check if there is a certificate associated to the given domain in the repository.
     *
     * @param string $domain
     *
     * @return bool
     */
    public function hasDomainCertificate($domain);

    /**
     * Load the certificate associated to a given domain.
     *
     * @param string $domain
     *
     * @throws AcmeCliException
     *
     * @return Certificate
     */
    public function loadDomainCertificate($domain);

    /**
     * Save a given string into a given path handling backup.
     *
     * @param string $path
     * @param string $content
     * @param string $visibility the visibilty to use for this file
     */
    public function save($path, $content, $visibility = self::VISIBILITY_PRIVATE);
}
