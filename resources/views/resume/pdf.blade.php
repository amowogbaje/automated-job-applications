<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px; }
        body { font-family: "Helvetica", Arial, sans-serif; font-size: 9.5pt; color: #1a1a1a; line-height: 1.35; }
        h1 { font-size: 17pt; margin: 0 0 2px; letter-spacing: .5px; }
        .headline { font-size: 10pt; color: #333; margin: 0 0 6px; }
        .contact { font-size: 8.5pt; color: #444; margin: 0 0 10px; }
        .contact span:after { content: " \00b7 "; color: #999; }
        .contact span:last-child:after { content: ""; }
        h2 { font-size: 10.5pt; text-transform: uppercase; letter-spacing: .6px; color: #14171F;
             border-bottom: 1.2px solid #14171F; padding-bottom: 2px; margin: 12px 0 6px; }
        .summary { margin: 0 0 4px; text-align: justify; }
        table.skills { width: 100%; border-collapse: collapse; }
        table.skills td { vertical-align: top; padding: 1.5px 0; font-size: 9pt; }
        table.skills td.cat { font-weight: bold; width: 24%; text-transform: capitalize; }
        .entry { margin-bottom: 7px; }
        .entry-head { width: 100%; }
        .entry-title { font-weight: bold; font-size: 9.8pt; }
        .entry-company { font-style: italic; }
        .entry-dates { float: right; font-size: 8.5pt; color: #555; }
        ul { margin: 2px 0 0; padding-left: 14px; }
        li { margin-bottom: 1.5px; }
        .proj-tech { color: #555; font-size: 8.3pt; }
        .clear { clear: both; }
    </style>
</head>
<body>

<h1>{{ $resume->full_name }}</h1>
@if($headline)
    <p class="headline">{{ $headline }}</p>
@endif

<p class="contact">
    @if($resume->email)<span>{{ $resume->email }}</span>@endif
    @if($resume->phone)<span>{{ $resume->phone }}</span>@endif
    @if($resume->location)<span>{{ $resume->location }}</span>@endif
    @if($resume->linkedin_url)<span>{{ $resume->linkedin_url }}</span>@endif
    @if($resume->website_url)<span>{{ $resume->website_url }}</span>@endif
    @if($resume->github_url)<span>{{ $resume->github_url }}</span>@endif
</p>

@if($resume->summary)
    <h2>Summary</h2>
    <p class="summary">{{ $resume->summary }}</p>
@endif

@if($skillsByCategory->isNotEmpty())
    <h2>Skills</h2>
    <table class="skills">
        @foreach($skillsByCategory as $category => $names)
            <tr>
                <td class="cat">{{ str_replace('_', ' ', $category) }}</td>
                <td>{{ $names->implode(', ') }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if($resume->experiences->isNotEmpty())
    <h2>Professional Experience</h2>
    @foreach($resume->experiences as $exp)
        <div class="entry">
            <div class="entry-head">
                <span class="entry-dates">{{ $exp->dateRangeLabel() }}</span>
                <span class="entry-title">{{ $exp->job_title }}</span>@if($exp->company) — <span class="entry-company">{{ $exp->company }}</span>@endif
                @if($exp->location) <span style="color:#666;">({{ $exp->location }})</span>@endif
            </div>
            <div class="clear"></div>
            @if(!empty($exp->bullets))
                <ul>
                    @foreach($exp->bullets as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
@endif

@if($projects->isNotEmpty())
    <h2>Selected Projects</h2>
    @foreach($projects as $proj)
        <div class="entry">
            <span class="entry-title">{{ $proj->name }}</span>
            @if(!empty($proj->tech_stack))
                <span class="proj-tech"> — {{ implode(', ', $proj->tech_stack) }}</span>
            @endif
            @if($proj->url)
                <span class="proj-tech"> · {{ $proj->url }}</span>
            @endif
            @if($proj->description)
                <ul><li>{{ $proj->description }}</li></ul>
            @endif
        </div>
    @endforeach
@endif

@if($resume->certifications->isNotEmpty())
    <h2>Certifications</h2>
    <p>{{ $resume->certifications->pluck('name')->implode(' · ') }}</p>
@endif

@if($resume->education->isNotEmpty())
    <h2>Education</h2>
    @foreach($resume->education as $edu)
        <div class="entry">
            <span class="entry-title">{{ $edu->institution }}</span>
            @if($edu->degree) — {{ $edu->degree }}@endif
            @if($edu->field), {{ $edu->field }}@endif
        </div>
    @endforeach
@endif

</body>
</html>
