<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Entity\BlogPosts;
use AppBundle\Entity\User;


use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Symfony\Component\HttpFoundation\Session\Session;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $session = new Session();
        $loggeduserid = $session->get('loggedUserId');

        if(!empty($loggeduserid)){
            return $this->redirectToRoute('blogpostlist');
        }

        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('UserName', TextType::class,['required' => false])
            ->add('Password', PasswordType::class,['required' => false])
            ->add('save', SubmitType::class, ['label' => 'Login'])
            ->getForm();

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $username = $user->getUsername();
            $password = $user->getPassword();

            $userpwd = $username.''.$password;
            $sha1userpwd = sha1($userpwd);

            $repository = $this->getDoctrine()->getRepository(User::class);

            $users = $repository->findOneBy(['saltpassword' => $sha1userpwd]);

            if(!empty($users)){
                $loggedUserId = $users->getId();

                $session->set('loggedUserId', $loggedUserId);
                $session->getFlashBag()->add('notice', 'Login Sucessfully !');

                return $this->redirectToRoute('blogpostlist');
            }else{
                $session->getFlashBag()->add('notice', 'Login not Sucessfully ! please check username and password'); 
            }


        }
    
        return $this->render('AppBundle:login:index.html.twig', [
            'loginform' => $form->createView(),
        ]);
    }


    /**
     * @Route("/blog", name="blogpostlist")
     */
    public function BloglistAction(Request $request)
    {
        $blogs = [];
        $session = new Session();
        
        $loggeduserid = $session->get('loggedUserId');
        $blogpostrepository = $this->getDoctrine()->getRepository(BlogPosts::class);
        $userrepository = $this->getDoctrine()->getRepository(User::class);
        $blogslist = $blogpostrepository->findAll();
        $username = '';

        if(!empty($blogslist)){

            foreach($blogslist as $bloginfo){
               $blogid = $bloginfo->getId();
               $content = $bloginfo->getContent();
               $post = $bloginfo->getPost();
               $userid = $bloginfo->getUserid();
               $users = $userrepository->find($userid);
               if(!empty($users)){
               $username = $users->getUserName();
               }

               $blogs[$blogid]['Id'] = $blogid;
               $blogs[$blogid]['Content'] = $content;
               $blogs[$blogid]['Post'] = $post;
               $blogs[$blogid]['BloggerName'] = $username;

            }
        }



        return $this->render('AppBundle:blog:list.html.twig',['blogs'=>$blogs]);


    }

    /**
     * @Route("/blog/add", name="addblogpost")
     */
    public function BlogAddAction(Request $request)
    {
        $session = new Session();
        $loggeduserid = $session->get('loggedUserId');

        if(empty($loggeduserid)){
            return $this->redirectToRoute('homepage');
        }
        
        $blog = new BlogPosts();
        $blog->setPost('Write a blog post');
        $blog->setContent('Write a blog post Content');

        $form = $this->createFormBuilder($blog)
            ->add('post', TextType::class)
            ->add('content', TextType::class)
            ->add('save', SubmitType::class, ['label' => 'Create Blog'])
            ->getForm();

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $blog = $form->getData();
            $blog->setUserid(1);
            $blog->setCreatedAt(new \DateTime());
    
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($blog);
            $entityManager->flush();
    
            return $this->redirectToRoute('blogpostlist');
        }
    

        return $this->render('AppBundle:blog:add.html.twig', [
            'form' => $form->createView(),
        ]);

    }

    /**
     * @Route("/blog/{blogid}", name="viewblogpost")
     */
    public function BlogViewAction($blogid)
    {
        $session = new Session();
        $loggeduserid = $session->get('loggedUserId');

        
        $blogpostrepository = $this->getDoctrine()->getRepository(BlogPosts::class);
        $userrepository = $this->getDoctrine()->getRepository(User::class);
        $bloginfo = $blogpostrepository->find($blogid);
        $roles = '';
    
        $bloguserid = $bloginfo->getUserid();
        $users = $userrepository->find($loggeduserid);
        if(!empty($users)){
            $roles = $users->getRoles();
        }
        $mode = 'readonly';
        $allowedRoles = ['ROLE_ADMIN'];
        if(!empty($roles)){
            if( ($bloguserid == $loggeduserid) || in_array($roles,$allowedRoles) ){
                $mode = 'editonly';
            }
        }

        if(!empty($bloginfo)){
          $blog = $bloginfo;
        }else{
            return $this->redirectToRoute('blogpostlist');
        }

        return $this->render('AppBundle:blog:blog.html.twig', [
            'blogs' => $blog,
            'mode' => $mode,
        ]);
    


    }

    /**
     * @Route("/blog/edit/{blogid}", name="editblogpost")
     */
    public function BlogEditAction($blogid,Request $request)
    {
        $session = new Session();
        $loggeduserid = $session->get('loggedUserId');
        
        $blogpostrepository = $this->getDoctrine()->getRepository(BlogPosts::class);
        $userrepository = $this->getDoctrine()->getRepository(User::class);
        $bloginfo = $blogpostrepository->find($blogid);
        $post = $bloginfo->getPost();
        $content = $bloginfo->getContent();
        $userid = $bloginfo->getUserId();
    
        $bloginfo->setPost($post);
        $bloginfo->setContent($content);

        $form = $this->createFormBuilder($bloginfo)
            ->add('post', TextType::class)
            ->add('content', TextType::class)
            ->add('save', SubmitType::class, ['label' => 'Update Blog'])
            ->getForm();

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $bloginfo = $form->getData();
            $bloginfo->setUserid($userid);
            $bloginfo->setCreatedAt(new \DateTime());
    
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($bloginfo);
            $entityManager->flush();
    
            return $this->redirectToRoute('blogpostlist');
        }

        return $this->render('AppBundle:blog:add.html.twig', [
            'form' => $form->createView(),
        ]);


    }

    /**
     * @Route("/logout", name="logout")
     */
    public function LogoutAction(Request $request)
    {
        $session = new Session();        
        $session->invalidate();

        return $this->redirectToRoute('homepage');
    


    }


}
