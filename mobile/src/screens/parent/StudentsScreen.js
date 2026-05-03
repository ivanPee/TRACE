import React from 'react';
import { Text } from 'react-native';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function StudentsScreen({ navigation }) {
  const { students } = useAppContext();

  return (
    <Screen>
      <HeaderBlock
        eyebrow="Student Accounts"
        title="Linked students under the parent account."
        subtitle="Each student should have a unique LRN and route information before ride booking."
      />

      {students.map((student) => (
        <SectionCard key={student.id} title={student.name} subtitle={`${student.schoolName} • ${student.gradeLevel}`}>
          <Pill label={`LRN: ${student.lrn}`} />
          <Text>Pickup: {student.pickupAddress}</Text>
          <Text>Drop-off: {student.dropoffAddress}</Text>
          <Text>Emergency Contact: {student.emergencyContact}</Text>
        </SectionCard>
      ))}

      <AppButton label="Add Student Account" onPress={() => navigation.navigate('AddStudent')} />
    </Screen>
  );
}
