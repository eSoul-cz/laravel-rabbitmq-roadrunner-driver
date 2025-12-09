pipeline {
	agent any
	stages {
		stage('Trigger Satis rebuild') {
			steps {
				build job: 'internal/eSoul packages', wait: false
			}
		}
	}
}
