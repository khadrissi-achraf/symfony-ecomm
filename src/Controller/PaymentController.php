<?php
namespace App\Controller;

use App\Entity\Order;
use App\Model\Cart;
use App\Repository\OrderRepository;
use App\Service\Mail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    #[Route('/commande/checkout/{reference}', name: 'checkout')]
    public function payment(OrderRepository $repository, $reference, EntityManagerInterface $em): Response
    {
        $order = $repository->findOneByReference($reference);
        if (!$order) {
            throw $this->createNotFoundException('Cette commande n\'existe pas');
        }

        // Marquer la commande comme payée (ou en attente de paiement)
        $order->setState(1); // 1 = payée
        $em->flush();

        // Rediriger vers la page de succès
        return $this->redirectToRoute('payment_success', [
            'reference' => $order->getReference()
        ]);
    }

    #[Route('/commande/valide/{reference}', name: 'payment_success')]
    public function paymentSuccess(OrderRepository $repository, $reference, EntityManagerInterface $em, Cart $cart)
    {
        $order = $repository->findOneByReference($reference);
        if (!$order || $order->getUser() != $this->getUser()) {
            throw $this->createNotFoundException('Commande inaccessible');
        }

        // Envoyer un e-mail de confirmation
        $user = $this->getUser();
        $content = "Bonjour {$user->getFirstname()}, nous vous remercions de votre commande.";
        (new Mail)->send(
            $user->getEmail(), 
            $user->getFirstname(), 
            "Confirmation de la commande {$order->getReference()}", 
            $content
        );

        // Supprimer le panier
        $cart->remove();

        return $this->render('payment/success.html.twig', [
            'order' => $order
        ]);
    }

    #[Route('/commande/echec/{reference}', name: 'payment_fail')]
    public function paymentFail(OrderRepository $repository, $reference)
    {
        $order = $repository->findOneByReference($reference);
        if (!$order || $order->getUser() != $this->getUser()) {
            throw $this->createNotFoundException('Commande inaccessible');
        }

        return $this->render('payment/fail.html.twig', [
            'order' => $order
        ]);
    }
}
?>