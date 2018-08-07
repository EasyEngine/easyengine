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
use AcmePhp\Core\Protocol\CertificateOrder;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface RepositoryV2Interface extends RepositoryInterface
{
    /**
     * Store a given certificate as associated to a given domain.
     *
     * @param array            $domains
     * @param CertificateOrder $order
     *
     * @throws AcmeCliException
     */
    public function storeCertificateOrder(array $domains, CertificateOrder $order);

    /**
     * Check if there is a certificate associated to the given domain in the repository.
     *
     * @param string $domain
     *
     * @return bool
     */
    public function hasCertificateOrder(array $domains);

    /**
     * Load the certificate associated to a given domain.
     *
     * @param string $domain
     *
     * @throws AcmeCliException
     *
     * @return CertificateOrder
     */
    public function loadCertificateOrder(array $domains);
}
