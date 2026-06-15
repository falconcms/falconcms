<?php

namespace FalconCms\Core\View\Components;

use Illuminate\View\Component;

class PostRenderer extends Component
{
    public $post;

    public function __construct($post)
    {
        $this->post = $post;
    }

    public function render()
    {
        if ($this->post->editor_type === 'builder') {
            return '<div>{!! get_lazy_content($post->content) !!}</div>';
        }

        return view('falcon-cms::components.rich-renderer');
    }
}
