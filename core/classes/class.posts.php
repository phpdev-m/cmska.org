<?php

//////////////////////////////////////////////////////////////////////////////////////////

if( !defined('GAUSS_CMS') ){ echo basename(__FILE__); exit; }

//////////////////////////////////////////////////////////////////////////////////////////

if( !trait_exists( 'basic' ) ){      require( CLASSES_DIR.DS.'trait.basic.php' ); }
if( !trait_exists( 'db_connect' ) ){ require( CLASSES_DIR.DS.'trait.db_connect.php' ); }
if( !class_exists( 'bbcode' ) ){     require( CLASSES_DIR.DS.'class.bbcode.php' ); }

//////////////////////////////////////////////////////////////////////////////////////////

class posts
{
    use basic, db_connect;

    public final function save( $data = false )
    {
      if( !$data || !is_array($data) || !count($data) )
      {
        return false;
      }
      $data = self::stripslashes( $data );

      $data['post:id']          = isset($data['post:id'])?          self::integer($data['post:id']):false;
      $data['categ:id']         = isset($data['categ:id'])?         self::integer($data['categ:id']):false;
      $data['post:alt_title']   = isset($data['post:alt_title'])?   self::totranslit($data['post:alt_title']):false;
      $data['post:title']       = isset($data['post:title'])?       self::trim($data['post:title']):false;
      $data['post:descr']       = isset($data['post:descr'])?       self::trim($data['post:descr']):false;
      $data['post:short_post']  = isset($data['post:short_post'])?  self::trim($data['post:short_post']):false;
      $data['post:full_post']   = isset($data['post:full_post'])?   self::trim($data['post:full_post']):false;
      $data['post:keywords']    = isset($data['post:keywords'])?    self::trim($data['post:keywords']):false;

      $_ID = self::integer( $data['post:id'] );

      if( !$data['post:alt_title'] || strlen($data['post:alt_title']) < 1 )
      {
          $data['post:alt_title'] = self::totranslit($data['post:title']);
      }

      $data['post:short_post'] = bbcode::bbcode2html( $data['post:short_post'] );
      $data['post:full_post']  = bbcode::bbcode2html( $data['post:full_post'] );

      $_2db = array();
      $_2db['title']            = $data['post:title'];
      $_2db['alt_title']        = $data['post:alt_title'];
      $_2db['descr']            = $data['post:descr'];
      $_2db['short_post']       = $data['post:short_post'];
      $_2db['full_post']        = $data['post:full_post'];
      $_2db['keywords']         = $data['post:keywords'];
      $_2db['category']         = $data['categ:id'];

      if( !$_ID ){ $_2db['author_id'] = CURRENT_USER_ID; }

      $_2db = array_map( array( &$this->db, 'safesql' ), $_2db );

      $SQL = '';
      if( $_ID )
      {
        foreach( $_2db as $k=>$v ){ $_2db[$k] = '"'.$k.'" = \''.$v.'\''; }
        $SQL = 'UPDATE posts SET '.implode( ', ', $_2db ).' WHERE id=\''.$_ID.'\' RETURNING id;';
      }
      else
      {
        $SQL = 'INSERT INTO posts ("'.implode('", "', array_keys($_2db)).'") VALUES (\''.implode('\', \'', array_values($_2db)).'\') RETURNING id;';
      }

      cache::clean( 'posts' );

      $_ID = $this->db->super_query( $SQL );
      $_ID = isset($_ID['id'])?self::integer($_ID['id']):0;
      echo $_ID;
      exit;
    }

    public final function listposts_html( $data = array(), &$tpl = false /*OBJECT*/, $skin = 'post_list' )
    {
      foreach( $data as $post_id => $value )
      {
        $tpl->load( $skin );

        $edit_url = '/index.php?mod='._MOD_.'&submod='._SUBMOD_.'&post_id='.$post_id;
        $tpl->set( '{edit_url}', $edit_url );

        foreach( array( 'post', 'categ', 'usr' ) as $_tag_group )
        {
          $_inf = &$value[$_tag_group];
          foreach( $_inf as $tag => $val )
          {
            $val = self::stripslashes($val);
            $val = self::htmlspecialchars( $val );
            $tpl->set( '{'.$_tag_group.':'.$tag.'}', $val );
          }
        }
        $tpl->compile( $skin );
      }
      return false;
    }

    public final static function get_tag_url( $tag )
    {
        if( !isset($GLOBALS['_TAGS']) || !is_object($GLOBALS['_TAGS']) )
        {
            $GLOBALS['_TAGS'] = new tags;
        }
        $_TAGS = &$GLOBALS['_TAGS'];

        return $_TAGS->get_url($tag);
    }

    public final static function get_url( &$data = array() )
    {
        if( !isset($GLOBALS['_CATEG']) || !is_object($GLOBALS['_CATEG']) )
        {
            $GLOBALS['_CATEG'] = new categ;
        }
        $_CATEG = &$GLOBALS['_CATEG'];

        $post_id = self::integer( $data['post']['id'] );
        $link = $_CATEG->get_url( self::integer( $data['categ']['id'] ) ).$data['post']['id'].'-'.self::totranslit( $data['post']['alt_title'] ).'.html';
        return $link;
    }

    public final function get( $filters = array(), &$count = false )
    {
        if( !is_array($filters) ){ $filters = array(); }

        $filters['nullpost']    = self::integer( (isset($filters['nullpost'])?$filters['nullpost']:0) ) ? true : false;
        $filters['offset']      = self::integer( (isset($filters['offset'])?$filters['offset']:0) );
        $filters['limit']       = self::integer( (isset($filters['limit'])?$filters['limit']:10) );
        $filters['full_data']   = self::integer( (isset($filters['full_data'])?$filters['full_data']:false) );
        $filters['uncache']     = self::integer( (isset($filters['uncache'])?$filters['uncache']:false) )? true : false;
        $filters['post.categ']  = self::integer( (isset($filters['post.categ'])?$filters['post.categ']:false) );
        $filters['post.id']     = self::integer( (isset($filters['post.id'])?$filters['post.id']:false) );
        $filters['tag.id']      = self::integer( (isset($filters['tag.id'])?$filters['tag.id']:false) );

        /////////////////
        if( $filters['nullpost'] )
        {
          $filters['limit'] = 1;
          $filters['offset'] = 0;
          $filters['full_data'] = true;
          $filters['post.categ'] = false;
          $filters['post.id'] = false;
          $filters['tag.id'] = false;
        }
        /////////////////

        $SELECT = array();
        $FROM   = array();
        $WHERE  = array();
        $ORDER  = array();

        $SELECT['posts.id']              = 'post.id';
        $SELECT['posts.title']           = 'post.title';
        $SELECT['posts.alt_title']       = 'post.alt_title';
        $SELECT['posts.descr']           = 'post.descr';
        $SELECT['posts.short_post']      = 'post.short_post';
        $SELECT['posts.author_id']       = 'post.author_id';
        $SELECT['posts.created_time']    = 'post.created_time';
        $SELECT['posts.keywords']        = 'post.keywords';

        $SELECT['categ.id']              = 'categ.id';
        $SELECT['categ.altname']        = 'categ.altname';
        $SELECT['categ.name']           = 'categ.name';

        $SELECT['usr.login']           = 'usr.login';
        $SELECT['usr.email']           = 'usr.email';

        if( $filters['full_data'] )
        {
            $SELECT['posts.full_post']        = 'post.full_post';
        }

        $FROM['posts']       = 'posts';
        $FROM['categories']  = 'LEFT JOIN categories as categ ON ( categ.id = posts.category )';
        $FROM['posts_tags']  = 'LEFT JOIN posts_tags as ptags ON ( ptags.post_id = posts.id AND ptags.tag_id > 0 )';
        $FROM['users']       = 'LEFT JOIN users as usr ON ( usr.id = posts.author_id )';

        if( !$filters['nullpost'] )
        {
          $WHERE['posts.id'] = 'posts.id > 0';
          $WHERE['categ.id'] = 'categ.id > 0';
        }
        else
        {
          $WHERE['posts.id'] = 'posts.id = 0';
        }

        if( $filters['post.categ'] > 0 )
        {
            $WHERE['categ.id'] = 'categ.id = '.$filters['post.categ'];
        }

        if( $filters['post.id'] > 0 )
        {
            $WHERE['post.id'] = 'posts.id = '.$filters['post.id'];
        }

        if( $filters['tag.id'] > 0 )
        {
            $WHERE['tags.id'] = 'ptags.tag_id = '.$filters['tag.id'];
        }

        $ORDER['posts.created_time'] = 'posts.created_time DESC';

        if( $SELECT && is_array($SELECT) && count($SELECT) )
        {
            foreach( $SELECT as $key => $name )
            {
                $SELECT[$key] = "\n\t".''.$key.' as "'.$name.'"';
            }
            $SELECT = implode( ', ', array_values($SELECT) );
        }
        else
        { common::err( 'Error with $SELECT directive!' ); }

        if( $FROM && is_array($FROM) && count($FROM) )
        {
            foreach( $FROM as $key => $name )
            {
                $FROM[$key] = "\n\t".''.$name;
            }
            $FROM = implode( '', array_values($FROM) );
        }
        else
        { common::err( 'Error with $FROM directive!' ); }

        $SQL =  'SELECT '."\n-- SELECT ".$SELECT."\n-- SELECT\n".
                'FROM '.$FROM."\n".
                'WHERE '."\n\t".implode( ' AND'."\n\t", $WHERE )."\n".
                "-- ORDER\n".
                'ORDER BY '.implode( ', ', array_values($ORDER) )."\n".
                "-- ORDER\n".
                'OFFSET '.$filters['offset'].' LIMIT '.$filters['limit'].';'."\n".
                ($filters['uncache']?'':self::trim(QUERY_CACHABLE))."\n-- USER_ID: ".abs(intval(CURRENT_USER_ID));

        $countSQL = preg_replace( '!-- SELECT(.+?)-- SELECT!is', ' count( posts.id ) as count ', $SQL );
        $countSQL = preg_replace( '!(OFFSET|LIMIT)(\s+?)(\d+)!is', '', $countSQL );
        $countSQL = preg_replace( '!-- ORDER(.+?)-- ORDER!is', '', $countSQL );

        $_var = 'posts-'.md5($SQL);
        $data = cache::get( $_var );

        if( !$data )
        {
            $data = array();
            $data['count'] = $this->db->get_count( $countSQL );
            $data['rows'] = array();

            $SQL  =  $this->db->query( $SQL );
            while( $row = $this->db->get_row($SQL) )
            {
                $row['post.created_time'] = self::en_date( $row['post.created_time'], 'Y.m.d H:i' );
                $data['rows'][$row['post.id']] = array();
                foreach( $row as $k => $v )
                {
                    $k = explode( '.', $k, 2 );
                    if( !isset($data['rows'][$row['post.id']][$k[0]]) ){ $data['rows'][$row['post.id']][$k[0]] = array(); }
                    $data['rows'][$row['post.id']][$k[0]][$k[1]] = $v;
                }
                // Get tags
                $tagSQL =   'SELECT tags.* '.
                            'FROM posts_tags as ptags '.
                            'LEFT JOIN tags as tags ON (tags.id = ptags.tag_id) '.
                            'WHERE tags.id > 0 AND ptags.post_id = '.self::integer($row['post.id']).
                            'ORDER by tags.name;';

                $tagSQL = $this->db->query( $tagSQL );

                $data['rows'][$row['post.id']]['tags'] = array();
                while( ($tag = $this->db->get_row($tagSQL))!=false )
                {
                    $data['rows'][$row['post.id']]['tags'][$tag['id']] = $tag;
                }

                //
            }

            cache::set( $_var, $data );
        }

        $count = $data['count'];
        return $data['rows'];
    }

    public final function html( $data = array(), &$tpl = false /*OBJECT*/, $skin = 'postshort' )
    {
        if( !isset($GLOBALS['_CATEG']) || !is_object($GLOBALS['_CATEG']) ){ $GLOBALS['_CATEG'] = new categ; }
        $_CATEG = &$GLOBALS['_CATEG'];

        $tpl->load( $skin );

        $data = self::stripslashes( $data );

        $tpl->set( '{post:id}',          self::integer($data['post']['id']));
        $tpl->set( '{post:url}',         self::get_url( $data ) );
        $tpl->set( '{post:author_id}',   self::integer($data['post']['author_id']));
        $tpl->set( '{post:short_post}',  self::trim( $data['post']['short_post'] ) );
        $tpl->set( '{post:created_time}',$data['post']['created_time'] );
        $tpl->set( '{categ:id}',         self::integer( $data['categ']['id'] ) );
        $tpl->set( '{categ:url}',        $_CATEG->get_url( self::integer($data['categ']['id']) ) );

        if( isset($data['post']['full_post']) ){ $tpl->set( '{post:full_post}',   self::trim( $data['post']['full_post'] ) ); }

        foreach( $data as $key => $value )
        {
            if( in_array( $key, array('post' , 'categ') ) && is_array($value) )
            {
                foreach( $value as $k=>$v )
                {
                    $tpl->set( '{'.$key.':'.$k.'}', self::htmlspecialchars( self::stripslashes($v) ) );
                }
            }
        }

        $tags = '';
        if( is_array($data['tags']) && count($data['tags']) )
        {
            $tags = array();
            foreach($data['tags'] as $val)
            {
                $val = self::stripslashes( $val );
                $val = self::trim( $val );
                $val = self::htmlspecialchars( $val );
                $tags[] = '<a rel="tag" href="'.self::get_tag_url($val['name']).'" title="'.$val['name'].'">'.$val['name'].'</a>';
            }
            $tags = implode( '', $tags );
        }

        $tpl->set( '{taglist}', $tags );

        $tpl->compile( $skin );
        return false;
    }

    public final function editpost_html( $data = array(), &$tpl = false /*OBJECT*/, $skin = 'post_edit' )
    {
      foreach( $data as $post_id => $value )
      {
        $tpl->load( $skin );

        $tpl->set( '{post:short_post}', bbcode::html2bbcode( self::stripslashes($value['post']['short_post']) ) );
        $tpl->set( '{post:full_post}', bbcode::html2bbcode( self::stripslashes($value['post']['full_post']) ) );

        foreach( $value as $_tag_group => $_inf )
        {
          foreach( $_inf as $tag => $val )
          {
            $val = self::stripslashes($val);
            $val = self::htmlspecialchars( $val );
            $tpl->set( '{'.$_tag_group.':'.$tag.'}', $val );
          }
        }
        $tpl->compile( $skin );
      }


      return false;
    }
}

?>