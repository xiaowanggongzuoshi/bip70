<?php

declare(strict_types=1);

namespace Bip70\Test\X509;

use Bip70\Exception\X509Exception;
use Bip70\Protobuf\Proto\X509Certificates;
use Bip70\X509\QualifiedCertificate;
use Bip70\X509\RequestValidation;
use Bip70\X509\TrustStoreLoader;
use PHPUnit\Framework\TestCase;
use Sop\CryptoEncoding\PEM;
use Sop\CryptoEncoding\PEMBundle;
use X509\Certificate\Certificate;
use X509\Certificate\CertificateBundle;
use X509\CertificationPath\CertificationPath;
use X509\CertificationPath\PathValidation\PathValidationConfig;

class QualifiedCertificateTest extends TestCase
{
    public function testCertificatesMustMatch()
    {
        $bundle = CertificateBundle::fromPEMBundle(PEMBundle::fromFile(__DIR__ . "/../../data/testnet-only-cert-not-valid.cabundle.pem"));
        $x509 = new X509Certificates();
        foreach ($bundle->all() as $it) {
            $x509->addCertificate($it->toDER());
        }

        // 10/12/2017 ish
        $now = new \DateTimeImmutable();
        $now = $now->setTimestamp(1509692666);

        $validationConfig = new PathValidationConfig($now, 10);
        $validator = new RequestValidation($validationConfig, TrustStoreLoader::fromSystem());
        $qualified = $validator->validateCertificateChain($x509);

        $selfCert = Certificate::fromPEM(PEM::fromFile(__DIR__ . "/../../data/selfsigned.cert.pem"));
        $selfBundle = new CertificateBundle($selfCert);
        $selfSignedPath = CertificationPath::toTarget($selfCert, $selfBundle);

        $this->expectExceptionMessage("CertificationPath entity certificate must match PathValidationResult certificate");
        $this->expectException(X509Exception::class);

        new QualifiedCertificate($selfSignedPath, $qualified->getValidationResult());
    }

    public function testSubject()
    {
        $bundle = CertificateBundle::fromPEMBundle(PEMBundle::fromFile(__DIR__ . "/../../data/testnet-only-cert-not-valid.cabundle.pem"));
        $x509 = new X509Certificates();
        foreach ($bundle->all() as $it) {
            $x509->addCertificate($it->toDER());
        }

        // 10/12/2017 ish
        $now = new \DateTimeImmutable();
        $now = $now->setTimestamp(1509692666);
        $validationConfig = new PathValidationConfig($now, 10);

        $validator = new RequestValidation($validationConfig, TrustStoreLoader::fromSystem());
        $qualified = $validator->validateCertificateChain($x509);
        $this->assertTrue(Certificate::fromDER($x509->getCertificate(0))->tbsCertificate()->subject()->equals($qualified->subject()));
        $this->assertTrue($qualified->getPath()->endEntityCertificate()->tbsCertificate()->subject()->equals($qualified->subject()));
    }
}
