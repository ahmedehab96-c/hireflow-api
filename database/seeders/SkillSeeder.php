<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public const SKILLS = [
        'PHP', 'Laravel', 'JavaScript', 'TypeScript', 'React', 'Vue.js', 'Node.js', 'Python',
        'Flutter', 'Dart', 'MySQL', 'PostgreSQL', 'AWS', 'Docker', 'Kubernetes', 'REST APIs',
        'Git', 'Figma', 'UI/UX Design', 'Data Analysis', 'SQL', 'Power BI', 'Agile/Scrum',
        'Project Management', 'Digital Marketing', 'SEO', 'Financial Modelling', 'Arabic', 'English',
    ];

    public function run(): void
    {
        foreach (self::SKILLS as $name) {
            Skill::firstOrCreate(['name' => $name]);
        }
    }
}
