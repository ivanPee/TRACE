import React from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function StudentsScreen({ navigation }) {
  const { currentRole, students } = useAppContext();
  const isParent = currentRole === 'parent';

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="students" />}>
      <HeaderBlock
        eyebrow={isParent ? 'Student Accounts' : 'Assigned Students'}
        title={isParent ? 'Linked students under the parent account.' : 'Children assigned to this driver route.'}
        subtitle={isParent ? 'Each student should have a unique LRN and route information before ride booking.' : 'Drivers can review route details here, while profile ownership stays with the parents.'}
      />

      {students.map((student) => (
        <SectionCard key={student.id} title={student.name} subtitle={`${student.schoolName} - ${student.gradeLevel}`}>
          <Pill label={`LRN: ${student.lrn}`} />
          <Text>Pickup: {student.pickupAddress}</Text>
          <Text>Drop-off: {student.dropoffAddress}</Text>
          <Text>Emergency Contact: {student.emergencyContact}</Text>
        </SectionCard>
      ))}

      {isParent ? <AppButton label="Add Student Account" onPress={() => navigation.navigate('AddStudent')} /> : null}
    </Screen>
  );
}
