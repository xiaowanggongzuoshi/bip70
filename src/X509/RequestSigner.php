<?php

declare(strict_types=1);

namespace Bip70\X509;

use Bip70\Protobuf\Codec\NonDiscardingBinaryCodec;
use Bip70\Protobuf\Proto\PaymentDetails;
use Bip70\Protobuf\Proto\PaymentRequest;
use Bip70\Protobuf\Proto\X509Certificates;
use Sop\CryptoBridge\Crypto;
use Sop\CryptoTypes\AlgorithmIdentifier\Feature\AsymmetricCryptoAlgorithmIdentifier;
use Sop\CryptoTypes\Asymmetric\PrivateKeyInfo;
use X509\Certificate\Certificate;
use X509\Certificate\CertificateBundle;

class RequestSigner implements RequestSignerInterface
{
    /**
     * @var Crypto
     */
    private $crypto;

    /**
     * RequestSigner constructor.
     * @param Crypto|null $crypto
     */
    public function __construct(Crypto $crypto = null)
    {
        $this->crypto = $crypto ?: Crypto::getDefault();
    }

    /**
     * @inheritdoc
     */
    public function sign(
        PaymentDetails $details,
        string $pkiType,
        PrivateKeyInfo $privateKey,
        Certificate $cert,
        CertificateBundle $intermediates
    ): PaymentRequest {
        if ($pkiType === PKIType::NONE) {
            throw new \UnexpectedValueException("Don't call sign with pki_type = none");
        }

        /** @var AsymmetricCryptoAlgorithmIdentifier $algOid */
        $algOid = $privateKey->algorithmIdentifier();
        $signAlgorithm = SignatureAlgorithmFactory::getSignatureAlgorithm($pkiType, $algOid);

        $x509Certs = new X509Certificates();
        $x509Certs->setCertificate($cert->toDER(), 0);
        foreach ($intermediates as $i => $intermediate) {
            $x509Certs->setCertificate($intermediate->toDER(), $i + 1);
        }

        $request = new PaymentRequest();
        $request->setPaymentDetailsVersion(1);
        $request->setPkiType($pkiType);
        $request->setPkiData($x509Certs->serialize());
        $request->setSerializedPaymentDetails($details->serialize());
        $request->setSignature('');

        $data = $request->serialize(new NonDiscardingBinaryCodec());
        $signature = $this->crypto->sign($data, $privateKey, $signAlgorithm);

        $request->setSignature($signature->bitString()->string());

        return $request;
    }
}
