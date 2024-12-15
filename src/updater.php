<?php

namespace WPXUp\Sdk;

class Updater {

    public function __construct() {
        
        add_filter( 'plugins_api', [$this, 'info'], 20, 3);

        add_filter( 'site_transient_update_plugins', [$this, 'update'] );
    }

    public function init() {}

    public function info() {
        
        if( 'plugin_information' !== $action ) {
            return $res;
        }

        if( plugin_basename( __DIR__ ) !== $args->slug ) {
            return $res;
        }

        $remote = wp_remote_get( 
            WPX_API_HOST . '/updates', 
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/json'
                ) 
            )
        );

        if( 
            is_wp_error( $remote )
            || 200 !== wp_remote_retrieve_response_code( $remote )
            || empty( wp_remote_retrieve_body( $remote ) )
        ) {
            return $res;	
        }

        $remote = json_decode( wp_remote_retrieve_body( $remote ) );
        
        $res = new stdClass();
        $res->name = $remote->name;
        $res->slug = $remote->slug;
        $res->author = $remote->author;
        $res->author_profile = $remote->author_profile;
        $res->version = $remote->version;
        $res->tested = $remote->tested;
        $res->requires = $remote->requires;
        $res->requires_php = $remote->requires_php;
        $res->download_link = $remote->download_url;
        $res->trunk = $remote->download_url;
        $res->last_updated = $remote->last_updated;
        $res->sections = array(
            'description' => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog' => $remote->sections->changelog
        );

        if( ! empty( $remote->sections->screenshots ) ) {
            $res->sections[ 'screenshots' ] = $remote->sections->screenshots;
        }

        $res->banners = array(
            'low' => $remote->banners->low,
            'high' => $remote->banners->high
        );
        
        return $res;
    }

    public function update( $transient ) {

        if ( empty( $transient->checked ) ) {
            return $transient;
        }
        
        $slug = dirname(plugin_basename( __FILE__ ));
    
        $remote = wp_remote_get( 
            WPX_API_HOST . '/updates?slug=' . $slug,
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/json'
                )
            )
        );
    
        if( 
            is_wp_error( $remote )
            || 200 !== wp_remote_retrieve_response_code( $remote )
            || empty( wp_remote_retrieve_body( $remote ) )
        ) {
            return $transient;	
        }
    
        $remote = json_decode( wp_remote_retrieve_body( $remote ) );
    
        if(
            $remote
            && version_compare( $this->version, $remote->version, '<' )
            && version_compare( $remote->requires, get_bloginfo( 'version' ), '<' )
            && version_compare( $remote->requires_php, PHP_VERSION, '<' )
        ) {
            $res = new stdClass();
            $res->slug = $remote->slug;
            $res->new_version = $remote->version;
            $res->tested = $remote->tested;
            $res->package = $remote->download_url;
            $transient->response[ $res->plugin ] = $res;
        }
    
        return $transient;
    }
}