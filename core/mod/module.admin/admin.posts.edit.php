<?php


//////////////////////////////////////////////////////////////////////////////////////////

if( !defined('GAUSS_CMS') ){ echo basename(__FILE__); exit; }

//////////////////////////////////////////////////////////////////////////////////////////

if( $post_id > 0 )
{
  $_POSTS = new posts;
  $posts_count = 0;

  $filter['post.id'] = $post_id;
  $filter['full_data'] = 1;

  $data = $_POSTS->get( $filter, $posts_count );
  if( !$posts_count )
  {
    $post_id = 0;
    $_REQUEST['post_id'] = 0;
    $tpl->info( '����� �� ��������!', '����� �� ��������!', 'warn' );
    $tpl->info( '����� �� ��������!', '����� �� ��������!', 'notice' );
    $tpl->info( '����� �� ��������!', '����� �� ��������!', 'good' );
    return ;
  }
}

$tpl->load(    'post_edit' );
$tpl->compile( 'post_edit' );

$tpl->load( 'page_item' );
$tpl->set( '{data}', $tpl->result( 'post_edit' ) );
$tpl->compile( 'page_item' );


?>