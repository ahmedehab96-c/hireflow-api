<?php

namespace Database\Seeders;

use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\Skill;
use Illuminate\Database\Seeder;

/** Fictional companies (reserved .example domains) with three postings each. */
class CompanyJobSeeder extends Seeder
{
    public function run(): void
    {
        $skills = Skill::pluck('id', 'name');

        foreach ($this->catalog() as $companyData) {
            $jobs = $companyData['jobs'];
            unset($companyData['jobs']);

            $company = Company::updateOrCreate(['name' => $companyData['name']], $companyData);

            foreach ($jobs as $jobData) {
                $skillNames = $jobData['skills'];
                unset($jobData['skills']);

                $job = Job::updateOrCreate(
                    ['company_id' => $company->id, 'title' => $jobData['title']],
                    $jobData + ['currency' => 'AED', 'status' => JobStatus::Active],
                );

                $job->skills()->sync($skills->only($skillNames)->values());
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Dunecrest Technologies',
                'website' => 'https://dunecrest.example',
                'industry' => 'Software Development',
                'location' => 'Dubai Internet City, Dubai',
                'description' => 'Dunecrest builds cloud-based operations software for hospitality and real-estate groups across the GCC. The 120-person team ships weekly and runs entirely on AWS.',
                'jobs' => [
                    [
                        'title' => 'Senior Laravel Developer',
                        'location' => 'Dubai, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 22000,
                        'salary_max' => 30000,
                        'application_url' => 'https://dunecrest.example/careers/senior-laravel-developer',
                        'description' => "We are looking for a Senior Laravel Developer to own core services of our property-management platform.\n\nResponsibilities:\n- Design and build REST APIs used by our web and mobile apps\n- Lead code reviews and mentor two mid-level engineers\n- Improve query performance on MySQL databases with tens of millions of rows\n- Work with product managers to break down features into deliverable milestones\n\nRequirements:\n- 5+ years of PHP experience, 3+ with Laravel\n- Strong understanding of queues, caching and database indexing\n- Experience writing automated tests (PHPUnit or Pest)\n- Comfortable with Docker-based local environments",
                        'skills' => ['PHP', 'Laravel', 'MySQL', 'REST APIs', 'Docker', 'Git'],
                    ],
                    [
                        'title' => 'Frontend Engineer (React)',
                        'location' => 'Dubai, UAE (Hybrid)',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 16000,
                        'salary_max' => 23000,
                        'description' => "Join our product squad to build fast, accessible dashboards used by hotel operations teams every day.\n\nYou will:\n- Build new features in React and TypeScript\n- Turn Figma designs into reusable components in our design system\n- Collaborate closely with backend engineers on API contracts\n- Monitor and improve Core Web Vitals\n\nYou have:\n- 3+ years of professional React experience\n- Solid TypeScript skills and an eye for detail\n- Experience with RTL layouts is a strong plus",
                        'skills' => ['React', 'TypeScript', 'JavaScript', 'Figma', 'Git'],
                    ],
                    [
                        'title' => 'DevOps Engineer',
                        'location' => 'Dubai, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 24000,
                        'salary_max' => 32000,
                        'description' => "Our platform team is hiring a DevOps Engineer to keep our infrastructure reliable, secure and cost-efficient.\n\nResponsibilities:\n- Maintain Kubernetes clusters and CI/CD pipelines\n- Manage infrastructure as code for AWS environments\n- Own monitoring, alerting and on-call runbooks\n- Partner with developers to improve deployment speed\n\nRequirements:\n- 4+ years in a DevOps or SRE role\n- Hands-on experience with AWS, Docker and Kubernetes\n- Scripting skills in Python or Bash",
                        'skills' => ['AWS', 'Docker', 'Kubernetes', 'Python', 'Git'],
                    ],
                ],
            ],
            [
                'name' => 'Marina Mobile Labs',
                'website' => 'https://marinamobile.example',
                'industry' => 'Mobile Apps',
                'location' => 'Dubai Marina, Dubai',
                'description' => 'Marina Mobile Labs is a product studio designing and building consumer mobile apps for retail, fitness and food-delivery brands in the region.',
                'jobs' => [
                    [
                        'title' => 'Flutter Mobile Engineer',
                        'location' => 'Dubai, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 15000,
                        'salary_max' => 22000,
                        'description' => "We need a Flutter Mobile Engineer to build polished iOS and Android apps for our clients.\n\nWhat you'll do:\n- Develop features end-to-end in Flutter and Dart\n- Integrate REST APIs, push notifications and payment SDKs\n- Write widget and integration tests\n- Publish releases to the App Store and Google Play\n\nWhat we're looking for:\n- 2+ years of production Flutter experience\n- Familiarity with state management (Riverpod or Bloc)\n- Bilingual Arabic/English UI experience is a plus",
                        'skills' => ['Flutter', 'Dart', 'REST APIs', 'Git', 'Arabic'],
                    ],
                    [
                        'title' => 'Product Designer (UI/UX)',
                        'location' => 'Dubai, UAE (Hybrid)',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 14000,
                        'salary_max' => 20000,
                        'description' => "Marina Mobile Labs is hiring a Product Designer to shape mobile experiences from discovery to launch.\n\nResponsibilities:\n- Run user interviews and usability tests\n- Produce wireframes, prototypes and high-fidelity designs in Figma\n- Maintain and extend our shared design system\n- Work daily with engineers to ensure quality implementation\n\nRequirements:\n- A portfolio showing shipped mobile products\n- 3+ years of product or UX design experience\n- Comfortable presenting design decisions to clients",
                        'skills' => ['Figma', 'UI/UX Design', 'English'],
                    ],
                    [
                        'title' => 'QA Automation Intern',
                        'location' => 'Dubai, UAE',
                        'employment_type' => EmploymentType::Internship,
                        'salary_min' => 4000,
                        'salary_max' => 5000,
                        'description' => "A six-month internship for a recent graduate interested in software quality.\n\nYou will:\n- Write manual and automated test cases for mobile apps\n- Report and track bugs with clear reproduction steps\n- Learn to build automated UI tests in our CI pipeline\n\nYou should:\n- Have a degree in Computer Science or a related field (or graduate this year)\n- Know the basics of programming in any language\n- Be detail-oriented and curious",
                        'skills' => ['Git', 'Agile/Scrum', 'English'],
                    ],
                ],
            ],
            [
                'name' => 'Falcon Ledger Finance',
                'website' => 'https://falconledger.example',
                'industry' => 'Fintech',
                'location' => 'Abu Dhabi Global Market, Abu Dhabi',
                'description' => 'Falcon Ledger provides digital accounting and invoicing tools for SMEs, regulated within ADGM. We serve over 8,000 businesses across the UAE.',
                'jobs' => [
                    [
                        'title' => 'Backend Engineer (Node.js)',
                        'location' => 'Abu Dhabi, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 20000,
                        'salary_max' => 28000,
                        'description' => "Help us build the payments and invoicing services that thousands of SMEs rely on.\n\nResponsibilities:\n- Build and maintain Node.js microservices\n- Design PostgreSQL schemas for financial data with strong consistency guarantees\n- Implement integrations with banking and payment partners\n- Contribute to security reviews and compliance controls\n\nRequirements:\n- 4+ years of backend development with Node.js and TypeScript\n- Strong SQL skills, ideally PostgreSQL\n- Experience in fintech or another regulated industry is a plus",
                        'skills' => ['Node.js', 'TypeScript', 'PostgreSQL', 'REST APIs', 'Docker'],
                    ],
                    [
                        'title' => 'Financial Analyst',
                        'location' => 'Abu Dhabi, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 15000,
                        'salary_max' => 21000,
                        'description' => "We are looking for a Financial Analyst to support budgeting, forecasting and investor reporting.\n\nResponsibilities:\n- Build and maintain three-statement financial models\n- Prepare monthly management reports and variance analysis\n- Support fundraising with data rooms and investor updates\n\nRequirements:\n- Bachelor's degree in Finance, Accounting or Economics\n- 2+ years in FP&A, audit or investment banking\n- Advanced Excel; Power BI experience is a plus",
                        'skills' => ['Financial Modelling', 'Data Analysis', 'Power BI', 'English'],
                    ],
                    [
                        'title' => 'Data Analyst',
                        'location' => 'Abu Dhabi, UAE (Hybrid)',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 13000,
                        'salary_max' => 18000,
                        'description' => "Turn product and customer data into insights that drive our roadmap.\n\nYou will:\n- Write SQL queries and build dashboards in Power BI\n- Define and track product KPIs with the product team\n- Analyse A/B tests and present findings to leadership\n\nYou have:\n- 2+ years in a data or business analyst role\n- Strong SQL and a working knowledge of Python\n- Clear written and verbal communication in English",
                        'skills' => ['SQL', 'Data Analysis', 'Power BI', 'Python', 'English'],
                    ],
                ],
            ],
            [
                'name' => 'Oasis Health Systems',
                'website' => 'https://oasishealth.example',
                'industry' => 'Healthcare Technology',
                'location' => 'Dubai Healthcare City, Dubai',
                'description' => 'Oasis Health Systems develops clinic-management and telemedicine software used by outpatient clinics in the UAE and Oman.',
                'jobs' => [
                    [
                        'title' => 'Full Stack Developer (Laravel & Vue)',
                        'location' => 'Dubai, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 17000,
                        'salary_max' => 24000,
                        'description' => "Build features for our clinic-management platform, from appointment booking to e-prescriptions.\n\nResponsibilities:\n- Develop backend features in Laravel and frontend features in Vue.js\n- Work with healthcare data while respecting privacy requirements\n- Write tests and participate in sprint planning\n\nRequirements:\n- 3+ years of full-stack experience with PHP and JavaScript\n- Experience with Laravel and Vue.js\n- Understanding of secure coding practices",
                        'skills' => ['PHP', 'Laravel', 'Vue.js', 'JavaScript', 'MySQL', 'Agile/Scrum'],
                    ],
                    [
                        'title' => 'Technical Project Manager',
                        'location' => 'Dubai, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 25000,
                        'salary_max' => 33000,
                        'description' => "Lead the delivery of software rollouts for hospital and clinic groups.\n\nResponsibilities:\n- Plan and run implementation projects from kickoff to go-live\n- Coordinate engineering, QA and client stakeholders\n- Manage scope, timelines, risks and budgets\n- Run Agile ceremonies for two delivery teams\n\nRequirements:\n- 5+ years managing software projects\n- PMP or Scrum certification preferred\n- Fluent English; Arabic is a strong advantage",
                        'skills' => ['Project Management', 'Agile/Scrum', 'English', 'Arabic'],
                    ],
                    [
                        'title' => 'Part-time Medical Content Writer',
                        'location' => 'Remote (UAE)',
                        'employment_type' => EmploymentType::PartTime,
                        'salary_min' => 6000,
                        'salary_max' => 8000,
                        'description' => "Write clear, accurate patient-education content for our telemedicine app and website.\n\nYou will:\n- Write and edit articles on common health topics in English and Arabic\n- Work with our medical reviewers to ensure accuracy\n- Optimise content for search engines\n\nYou have:\n- A background in healthcare, life sciences or medical writing\n- Excellent writing skills in English and Arabic\n- Basic understanding of SEO",
                        'skills' => ['SEO', 'English', 'Arabic'],
                    ],
                ],
            ],
            [
                'name' => 'Coralwave Logistics',
                'website' => 'https://coralwave.example',
                'industry' => 'Logistics & Supply Chain',
                'location' => 'Sharjah Airport Free Zone, Sharjah',
                'description' => 'Coralwave is a tech-enabled freight and last-mile delivery company operating a fleet of 600 vehicles across the Northern Emirates.',
                'jobs' => [
                    [
                        'title' => 'Python Backend Developer',
                        'location' => 'Sharjah, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 16000,
                        'salary_max' => 22000,
                        'description' => "Build the routing and dispatch services that move thousands of parcels every day.\n\nResponsibilities:\n- Develop Python services for route optimisation and driver dispatch\n- Expose APIs consumed by our driver and customer apps\n- Optimise PostgreSQL queries for real-time tracking data\n\nRequirements:\n- 3+ years of backend development with Python\n- Experience with PostgreSQL and REST API design\n- Familiarity with Docker and AWS",
                        'skills' => ['Python', 'PostgreSQL', 'REST APIs', 'Docker', 'AWS'],
                    ],
                    [
                        'title' => 'Digital Marketing Specialist',
                        'location' => 'Sharjah, UAE',
                        'employment_type' => EmploymentType::FullTime,
                        'salary_min' => 10000,
                        'salary_max' => 14000,
                        'description' => "Grow our B2B and e-commerce customer base through performance marketing.\n\nYou will:\n- Plan and run paid campaigns on search and social channels\n- Own our website SEO and content calendar\n- Report on campaign performance and customer acquisition cost\n\nYou have:\n- 2+ years of digital marketing experience, ideally B2B\n- Hands-on experience with SEO and analytics tools\n- Strong copywriting in English; Arabic is a plus",
                        'skills' => ['Digital Marketing', 'SEO', 'Data Analysis', 'English', 'Arabic'],
                    ],
                    [
                        'title' => 'Operations Data Contractor',
                        'location' => 'Remote (UAE)',
                        'employment_type' => EmploymentType::Contract,
                        'salary_min' => 18000,
                        'salary_max' => 22000,
                        'description' => "A six-month contract to build operational reporting for our fleet and warehouse teams.\n\nScope:\n- Consolidate data from our dispatch system and warehouse tools\n- Build Power BI dashboards for delivery performance and fleet utilisation\n- Document data definitions and hand over to the internal team\n\nRequirements:\n- Proven experience delivering BI projects\n- Strong SQL and Power BI skills\n- Logistics domain experience is a plus",
                        'skills' => ['SQL', 'Power BI', 'Data Analysis'],
                    ],
                ],
            ],
        ];
    }
}
