<?php

namespace App\Academies;

class AcademyWebsiteDefaults
{
    /** @return array<string, array{label: string, description: string, fields: array<string, array<string, mixed>>}> */
    public static function definitions(): array
    {
        return [
            'topbar' => self::section('Contact bar', 'Contact details, social profiles and quick links above the main navigation.', [
                'phone' => self::text('Phone number', ''),
                'email' => self::text('Email address', ''),
                'socials' => self::lines('Social links', '', 'One per line: Label | URL'),
                'quick_links' => self::lines('Quick links', "Apply now | /apply\nProgrammes | /programmes", 'One per line: Label | URL'),
            ]),
            'announcement' => self::section('Announcement marquee', 'A moving announcement strip for admissions, events or important updates.', [
                'text' => self::textarea('Announcement', 'Admissions are open. Explore our programmes and begin your application.'),
            ]),
            'header' => self::section('Header and navigation', 'Uses the academy logo and builds navigation from enabled sections.', [
                'application_label' => self::text('Application button label', 'Apply now'),
            ]),
            'hero' => self::section('Hero', 'Use rotating slides or a direct video file URL.', [
                'eyebrow' => self::text('Eyebrow', 'Learn. Grow. Belong.'),
                'headline' => self::text('Fallback headline', 'Where potential becomes progress.'),
                'description' => self::textarea('Fallback description', 'A welcoming academy experience built around purposeful learning, expert guidance and a community that helps every learner thrive.'),
                'media_type' => self::select('Hero format', 'slides', ['slides' => 'Slides', 'video' => 'Video']),
                'video_url' => self::text('Direct video URL', '', 'Use an HTTPS link to an MP4 or WebM video.'),
                'slides' => self::lines('Slides', "Where potential becomes progress. | Discover programmes designed for purposeful growth. |  | Explore programmes | /programmes\nYour next chapter starts here. | Meet the people, places and experiences that make our academy special. |  | Apply now | /apply", 'One per line: Heading | Description | Image URL | Button label | Button URL'),
            ]),
            'about' => self::section('About the academy', 'Introduce the academy and its purpose.', [
                'body' => self::textarea('Overview', 'We create an environment where learners can build skill, confidence and character through structured programmes and committed support.'),
                'mission' => self::textarea('Mission', 'To help every learner develop practical ability, confidence and a lifelong commitment to growth.'),
                'vision' => self::textarea('Vision', 'To be a trusted academy where potential is recognised, nurtured and transformed into meaningful progress.'),
                'objectives' => self::lines('Objectives', "Deliver structured, engaging programmes\nSupport progress with capable instructors\nBuild a safe and inclusive learning community", 'One objective per line'),
                'motto' => self::text('Motto', 'Learn with purpose. Grow with confidence.'),
            ]),
            'welcome' => self::section('Founder welcome', 'A welcome message from the founder or academy leader.', [
                'message' => self::textarea('Welcome message', 'Welcome to our academy. We believe the best learning happens when ambition is met with the right guidance, environment and community. We look forward to being part of your journey.'),
                'name' => self::text('Name', 'Academy Leadership'),
                'role' => self::text('Role', 'Founder / Director'),
                'image_url' => self::text('Portrait URL', ''),
            ]),
            'programmes' => self::section('Our programmes', 'Displays active programmes already configured in Bootcademy.', [
                'intro' => self::textarea('Introduction', 'Explore structured pathways created for different goals, stages and interests.'),
            ]),
            'locations' => self::section('Our locations', 'Displays active academy branches and their contact information.', [
                'intro' => self::textarea('Introduction', 'Find the learning location that works best for you.'),
            ]),
            'experience' => self::section('Learning experience / Why choose us', 'Explain what makes the academy experience distinctive.', [
                'intro' => self::textarea('Introduction', 'Every part of the experience is designed to help learners participate, practise and progress.'),
                'items' => self::lines('Experience points', "Structured learning | Clear pathways and practical sessions keep progress focused.\nSupportive community | Learners grow with guidance, encouragement and accountability.\nProgress that matters | Programmes connect knowledge with confident real-world application.", 'One per line: Title | Description'),
            ]),
            'events' => self::section('Upcoming events', 'Publish upcoming open days, sessions and community activities.', [
                'intro' => self::textarea('Introduction', 'Join an upcoming academy event.'),
                'items' => self::lines('Events', '', 'One per line: Date | Title | Description | Location'),
            ], false),
            'news' => self::section('Featured news / Blog posts', 'Feature academy stories and announcements.', [
                'intro' => self::textarea('Introduction', 'Stories, updates and useful ideas from our community.'),
                'items' => self::lines('Articles', '', 'One per line: Date | Title | Excerpt | Link | Image URL'),
            ], false),
            'statistics' => self::section('Statistics', 'Show verified academy numbers as prominent counters.', [
                'items' => self::lines('Statistics', '', 'One per line: Number | Label'),
            ], false),
            'team' => self::section('Our team', 'Only add team members who have consented to public display.', [
                'intro' => self::textarea('Introduction', 'Meet the people guiding our learning community.'),
                'items' => self::lines('Team members', '', 'One per line: Name | Role | Short biography | Image URL'),
            ], false),
            'testimonials' => self::section('Testimonials', 'Publish genuine testimonials with permission.', [
                'items' => self::lines('Testimonials', '', 'One per line: Quote | Name | Relationship or role'),
            ], false),
            'alumni' => self::section('Alumni / Students', 'Celebrate learner stories with appropriate consent.', [
                'intro' => self::textarea('Introduction', 'Stories from learners who have been part of our academy.'),
                'items' => self::lines('Profiles', '', 'One per line: Name | Achievement or story | Image URL'),
            ], false),
            'gallery' => self::section('Gallery', 'Show academy life using approved public image URLs.', [
                'items' => self::lines('Gallery images', '', 'One per line: Caption | Image URL'),
            ], false),
            'affiliations' => self::section('Memberships / Affiliations', 'Display organisations the academy is genuinely affiliated with.', [
                'items' => self::lines('Affiliations', '', 'One per line: Name | Logo URL | Website URL'),
            ], false),
            'sponsors' => self::section('Sponsors', 'Display approved sponsor logos and links.', [
                'items' => self::lines('Sponsors', '', 'One per line: Name | Logo URL | Website URL'),
            ], false),
            'faq' => self::section('Frequently asked questions', 'Answer common questions before an applicant contacts the academy.', [
                'items' => self::lines('Questions and answers', '', 'One per line: Question | Answer'),
            ], false),
            'footer' => self::section('Footer', 'Closing text, contact information and navigation.', [
                'summary' => self::textarea('Academy summary', 'A purposeful place to learn, practise and grow.'),
                'copyright' => self::text('Copyright line', ''),
            ]),
            'whatsapp' => self::section('WhatsApp floating button', 'A radiating contact button fixed to the bottom-right of the public website.', [
                'phone' => self::text('WhatsApp number', '', 'Include the international country code.'),
                'message' => self::text('Default message', 'Hello, I would like to learn more about your academy.'),
                'label' => self::text('Callout label', 'Chat with us'),
            ]),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function sections(): array
    {
        return collect(self::definitions())->map(fn (array $section): array => collect($section['fields'])
            ->mapWithKeys(fn (array $field, string $key): array => [$key => $field['default']])
            ->prepend($section['title'], 'title')
            ->prepend($section['enabled'], 'enabled')
            ->all())->all();
    }

    /** @param array<string, array<string, mixed>>|null $sections
     * @return array<string, array<string, mixed>>
     */
    public static function merge(?array $sections): array
    {
        return collect(self::sections())->mapWithKeys(function (array $defaults, string $key) use ($sections): array {
            $stored = is_array($sections[$key] ?? null) ? $sections[$key] : [];
            $merged = array_replace($defaults, array_intersect_key($stored, $defaults));
            $merged['enabled'] = filter_var($merged['enabled'], FILTER_VALIDATE_BOOLEAN);

            return [$key => $merged];
        })->all();
    }

    /** @param array<string, array<string, mixed>> $fields
     * @return array{label: string, description: string, enabled: bool, title: string, fields: array<string, array<string, mixed>>}
     */
    private static function section(string $label, string $description, array $fields, bool $enabled = true): array
    {
        return ['label' => $label, 'description' => $description, 'enabled' => $enabled, 'title' => $label, 'fields' => $fields];
    }

    /** @return array<string, mixed> */
    private static function text(string $label, string $default, string $help = ''): array
    {
        return compact('label', 'default', 'help') + ['type' => 'text'];
    }

    /** @return array<string, mixed> */
    private static function textarea(string $label, string $default, string $help = ''): array
    {
        return compact('label', 'default', 'help') + ['type' => 'textarea'];
    }

    /** @return array<string, mixed> */
    private static function lines(string $label, string $default, string $help): array
    {
        return compact('label', 'default', 'help') + ['type' => 'lines'];
    }

    /** @param array<string, string> $options
     * @return array<string, mixed>
     */
    private static function select(string $label, string $default, array $options): array
    {
        return compact('label', 'default', 'options') + ['type' => 'select', 'help' => ''];
    }
}
