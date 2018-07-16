@extends('laramie::layout')

@section('content')
    <div class="column">
        <h1 class="title is-1">Welcome to your dashboard!</h1>
        <div class="columns">
            <div class="column is-half">
                <div class="content">
                    <p>Sorry it's a bit spartan... But Your application is unique, one-of-a-kind, singular... you get it --
                    a generic admin dashboard <em>may</em> not meet your needs. But no worries, you have a few
                    options:</p>

                    <p>If a rather generic admin dashboard <em>will</em> meet your needs, set the laramie config
                    parameter `dashboard_override` to 'vanilla'. As the name implies, doing so will enable the 'vanilla'
                    dashboard (which is nothing too fancy, but it does give your users something to look at and interact
                    with, enabling them to quickly list and add content).</p>

                    <p>If you don't need a dashboard at all (perhaps jumping directly to a model's list page would
                    suffice), simply set the `dashboard_override` config option mentioned above to the name of the model
                    whose list page you'd like to use instead. Once done, that page will serve as your dashboard.</p>

                    <p>Documentation is coming soon on how to configure your dashboard if something more involved is needed.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
