<?php

namespace App\Controller;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Persistence\ManagerRegistry;
use App\Form\EditProfileType;
use App\Form\AdminType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


#[Route('admin')]
class AdminController extends AbstractController
{

    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    #[Route('/', name: 'app_admin')]
    public function index(Security $security,UserRepository $userRepository): Response
    {
        if ($security->getUser()) {
            if (in_array("ROLE_ADMIN", $security->getUser()->getRoles())) {
                return $this->render('admin/index.html.twig', ['controller_name' => 'AdminController',
                    'user'=>$userRepository->findAll()]);
            } else {
                return $this->redirectToRoute("app_login");

            }
        } else {
            return $this->redirectToRoute("app_login");
        }
    }





    #[Route('profile/modifier', name: 'adminProfile',methods: ['GET', 'POST'])]

    public function AdminProfile(Security $security,ManagerRegistry $doctrine, Request $request, UserRepository $repository, SluggerInterface $slugger): response
    {

        $user = $this->getUser();
        $form = $this->createForm(EditprofileType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();


            $photo = $form->get('image')->getData();

            // this condition is needed because the 'brochure' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($photo) {
                $originalFilename = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photo->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $photo->move(
                        $this->getParameter('app.path.product_images'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $user->setImage($newFilename);
            }
            $em = $doctrine->getManager();
            $em->persist($user);
            $em->flush();

            $this->addFlash('message', 'Profil mis à jour');
            return $this->redirectToRoute('adminbacks');
        }

        return $this->render('admin/adminProfile.html.twig', [
            'form' => $form->createView(),

        ]);
    }




    #[Route('delete/{id}', name: 'DeleteAdmin',methods: ['GET', 'POST'])]

    public function RemoveAdmin(EntityManagerInterface $em, $id, UserRepository $repository,ManagerRegistry $doctrine)
    {
     

        $user=$repository->find($id);
        $em=$doctrine->getManager();
        $user->setRoles(['ROLE_USER']);
        $user->setImage('user.jpg');
        $user->setBanned(0);
        $em->flush();
        return $this->redirectToRoute('adminbacks');
    }

    #[Route('/admin', name: 'adminbacks',methods: ['GET', 'POST'])]

    public function AddAdmin(Request $request, ManagerRegistry $doctrine,UserRepository $repository)
    {
        $user = new User();     
        $userClient = $repository->findByRole('ROLE_ADMIN');
        $form = $this->createForm(AdminType::class, $user);
        // $form->add('Add',SubmitType::class);

        $form->handleRequest($request);
        $errors = $form->getErrors();

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the new users password
            $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));

            // Set their role
            $user->setRoles(['ROLE_ADMIN']);


            // Save
            $em = $doctrine->getManager();
            $em->persist($user);
            $em->flush();
       
            return $this->redirectToRoute('admin');
        }
        return $this->render('admin/index.html.twig', [
            'form' => $form->createView(),
            'errors' => $errors,
            'user'=>$user = $repository->findAll(),
            'userClient'=>$userClient
        ]);
    }

    #[Route(path: 'testadmin', name: 'testadmin')]
    public function test(): Response
    {

        return $this->render('baseadmin.html.twig');
    }
}
